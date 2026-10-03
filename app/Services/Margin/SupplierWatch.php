<?php

namespace App\Services\Margin;

use App\Models\InvoiceLine;
use App\Models\Supplier;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Flags suppliers who raise prices faster than their market and drafts a renegotiation message
 * built only from the numbers found in the evidence.
 */
final class SupplierWatch
{
    public function __construct(private GroundingCheck $groundingCheck) {}

    /**
     * @return list<SupplierFlag>
     */
    public function flags(CompanyProfile $profile): array
    {
        $window = MonthlySeries::window($profile->asOf, (int) config('margin.supplier_watch.window_months'));
        $threshold = (float) config('margin.supplier_watch.excess_threshold');

        $invoices = InvoiceLine::query()
            ->where('company_id', $profile->company->id)
            ->whereNotNull('supplier_id')
            ->whereNotNull('cost_line_id')
            ->whereBetween('invoiced_on', [CarbonImmutable::createFromFormat('!Y-m', $window[0]), CarbonImmutable::createFromFormat('!Y-m', end($window))->endOfMonth()])
            ->with('supplier')
            ->get(['supplier_id', 'cost_line_id', 'invoiced_on', 'quantity', 'total']);

        $flags = [];

        foreach ($invoices->groupBy(fn (InvoiceLine $invoice): string => $invoice->supplier_id.':'.$invoice->cost_line_id) as $group) {
            $line = $profile->line($group->first()->cost_line_id);

            if ($line === null || $line->driverCode() === null || $line->estimate === null) {
                continue;
            }

            $flag = $this->evaluate($profile, $line, $group->first()->supplier, $group->all(), $threshold);

            if ($flag !== null) {
                $flags[] = $flag;
            }
        }

        usort($flags, fn (SupplierFlag $a, SupplierFlag $b): int => $b->monthlyOvercharge <=> $a->monthlyOvercharge);

        return $flags;
    }

    /**
     * @param  list<InvoiceLine>  $invoices
     */
    private function evaluate(CompanyProfile $profile, CostLineProfile $line, Supplier $supplier, array $invoices, float $threshold): ?SupplierFlag
    {
        $quantities = [];
        $totals = [];

        foreach ($invoices as $invoice) {
            $period = MonthlySeries::key($invoice->invoiced_on->toImmutable());
            $quantities[$period] = ($quantities[$period] ?? 0.0) + $invoice->quantity;
            $totals[$period] = ($totals[$period] ?? 0.0) + $invoice->total;
        }

        ksort($totals);
        $periods = array_keys($totals);

        if (count($periods) < 4) {
            return null;
        }

        $early = array_slice($periods, 0, 2);
        $late = array_slice($periods, -2);
        $unitPrice = fn (array $months): float => array_sum(array_intersect_key($totals, array_flip($months))) / array_sum(array_intersect_key($quantities, array_flip($months)));
        $supplierChange = $unitPrice($late) / $unitPrice($early) - 1;

        $driverSeries = $profile->driverSeries[$line->driverCode()] ?? [];
        $lag = $line->estimate->lagMonths;
        $shiftAll = fn (array $months): array => array_map(fn (string $period): string => MonthlySeries::shift($period, -$lag), $months);
        $driverBefore = MonthlySeries::averageAt($driverSeries, $shiftAll($early));
        $driverAfter = MonthlySeries::averageAt($driverSeries, $shiftAll($late));

        if ($driverBefore === null || $driverAfter === null || $driverBefore <= 0) {
            return null;
        }

        $marketChange = $driverAfter / $driverBefore - 1;
        $expectedChange = $line->estimate->passThrough * $marketChange;
        $excess = $supplierChange - $expectedChange;

        if ($excess < $threshold) {
            return null;
        }

        $lateQuantity = array_sum(array_intersect_key($quantities, array_flip($late))) / count($late);
        $lateSpend = array_sum(array_intersect_key($totals, array_flip($late))) / count($late);
        $monthlyOvercharge = $lateSpend * (1 - (1 + $expectedChange) / (1 + $supplierChange));

        return new SupplierFlag(
            supplier: $supplier,
            line: $line,
            fromPeriod: $early[0],
            toPeriod: end($late),
            supplierChange: $supplierChange,
            marketChange: $marketChange,
            expectedChange: $expectedChange,
            excessChange: $excess,
            monthlyOvercharge: $monthlyOvercharge,
            monthlyQuantity: $lateQuantity,
            renegotiationDraft: $this->drafts($supplier, $line, $early[0], $supplierChange, $marketChange, $lateQuantity),
        );
    }

    /**
     * @return array{en: string, sq: string}
     */
    private function drafts(Supplier $supplier, CostLineProfile $line, string $fromPeriod, float $supplierChange, float $marketChange, float $quantity): array
    {
        $since = CarbonImmutable::createFromFormat('!Y-m', $fromPeriod);
        $supplierPercent = round($supplierChange * 100, 1);
        $marketPercent = round($marketChange * 100, 1);
        $roundedQuantity = round($quantity);
        $unit = $line->costLine->unit ?? 'units';
        $driverName = $line->costLine->priceDriver->name;
        $lineName = mb_strtolower($line->costLine->name);
        $formattedQuantity = number_format($roundedQuantity);

        $drafts = [
            'en' => "Hello {$supplier->name}, we have reviewed our {$lineName} purchases. Since {$since->locale('en')->translatedFormat('F Y')} your price has risen {$supplierPercent}%, while the {$driverName} benchmark moved {$marketPercent}% over the same period. We would like to discuss bringing our price back in line with the market. Our monthly volume is about {$formattedQuantity} {$unit}. Could we schedule a call this week?",
            'sq' => "Përshëndetje {$supplier->name}, kemi rishikuar blerjet tona të artikullit \"{$lineName}\". Që nga {$since->locale('sq')->translatedFormat('F Y')}, çmimi juaj është rritur {$supplierPercent}%, ndërsa treguesi {$driverName} ka lëvizur {$marketPercent}% në të njëjtën periudhë. Do të donim të diskutonim kthimin e çmimit në linjë me tregun. Vëllimi ynë mujor është rreth {$formattedQuantity} {$unit}. A mund të caktojmë një takim këtë javë?",
        ];

        $facts = [$supplierPercent, $marketPercent, $roundedQuantity, (int) $since->format('Y')];

        foreach ($drafts as $draft) {
            $ungrounded = $this->groundingCheck->ungroundedNumbers($draft, $facts);

            if ($ungrounded !== []) {
                throw new RuntimeException('Renegotiation draft contains ungrounded numbers: '.implode(', ', $ungrounded));
            }
        }

        return $drafts;
    }
}
