<?php

namespace App\Services\Margin;

use App\Models\Company;
use App\Models\CostLine;
use App\Models\DriverProjection;
use App\Models\InvoiceLine;
use App\Models\PriceDriver;
use App\Models\PriceObservation;
use App\Services\Margin\Simulation\DriverOutlook;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Reads a company's invoices, sales and the public driver data, and turns them into a CompanyProfile.
 */
final class CompanyProfileBuilder
{
    public function __construct(private PassThroughEstimator $estimator) {}

    public function build(Company $company): CompanyProfile
    {
        $invoiceLines = $company->invoiceLines()
            ->whereNotNull('cost_line_id')
            ->get(['cost_line_id', 'invoiced_on', 'quantity', 'total']);
        $financials = $company->monthlyFinancials()->orderBy('period')->get();

        $asOf = $this->asOf($invoiceLines, $financials->max('period'));
        $baselinePeriods = MonthlySeries::window($asOf, (int) config('margin.simulation.baseline_months'));

        $costLines = $company->costLines()->with('priceDriver')->orderBy('id')->get();
        $drivers = $this->drivers($costLines);
        $driverSeries = $this->driverSeries($drivers);

        $invoicesByLine = $invoiceLines->groupBy('cost_line_id');
        $lines = [];

        foreach ($costLines as $costLine) {
            $profile = $this->lineProfile($costLine, $invoicesByLine->get($costLine->id, collect()), $baselinePeriods, $driverSeries, $asOf);

            if ($profile !== null) {
                $lines[] = $profile;
            }
        }

        $revenue = $financials->mapWithKeys(fn ($row): array => [MonthlySeries::key($row->period->toImmutable()) => $row->revenue])->all();
        $sellingPriceIndex = $financials->mapWithKeys(fn ($row): array => [MonthlySeries::key($row->period->toImmutable()) => $row->selling_price_index])->all();

        return new CompanyProfile(
            company: $company,
            asOf: $asOf,
            currentMonthlyRevenue: $this->currentRevenue($revenue, $sellingPriceIndex, $baselinePeriods),
            lines: $lines,
            revenue: $revenue,
            sellingPriceIndex: $sellingPriceIndex,
            drivers: $drivers,
            driverSeries: $driverSeries,
            driverOutlooks: $this->outlooks($drivers, $driverSeries),
        );
    }

    /**
     * The latest month the company has data for.
     *
     * @param  Collection<int, InvoiceLine>  $invoiceLines
     */
    private function asOf(Collection $invoiceLines, mixed $latestFinancialPeriod): string
    {
        $latest = $invoiceLines->max('invoiced_on') ?? $latestFinancialPeriod ?? now();

        return MonthlySeries::key(CarbonImmutable::parse($latest));
    }

    /**
     * Drivers used by the cost lines, plus the headline CPI for comparison.
     *
     * @param  Collection<int, CostLine>  $costLines
     * @return array<string, PriceDriver>
     */
    private function drivers(Collection $costLines): array
    {
        $codes = $costLines->pluck('priceDriver.code')->filter()->push('cpi.headline')->unique()->values();

        return PriceDriver::query()
            ->whereIn('code', $codes)
            ->with(['projections' => fn ($query) => $query->orderByDesc('horizon_months')])
            ->get()
            ->keyBy('code')
            ->all();
    }

    /**
     * @param  array<string, PriceDriver>  $drivers
     * @return array<string, array<string, float>>
     */
    private function driverSeries(array $drivers): array
    {
        $series = array_fill_keys(array_keys($drivers), []);
        $codesById = [];

        foreach ($drivers as $code => $driver) {
            $codesById[$driver->id] = $code;
        }

        $observations = PriceObservation::query()
            ->whereIn('price_driver_id', array_keys($codesById))
            ->orderBy('period')
            ->get(['price_driver_id', 'period', 'value']);

        foreach ($observations as $observation) {
            $series[$codesById[$observation->price_driver_id]][MonthlySeries::key($observation->period->toImmutable())] = $observation->value;
        }

        return $series;
    }

    /**
     * @param  Collection<int, InvoiceLine>  $invoices
     * @param  list<string>  $baselinePeriods
     * @param  array<string, array<string, float>>  $driverSeries
     */
    private function lineProfile(CostLine $costLine, Collection $invoices, array $baselinePeriods, array $driverSeries, string $asOf): ?CostLineProfile
    {
        $quantities = [];
        $spend = [];

        foreach ($invoices as $invoice) {
            $period = MonthlySeries::key($invoice->invoiced_on->toImmutable());
            $quantities[$period] = ($quantities[$period] ?? 0.0) + $invoice->quantity;
            $spend[$period] = ($spend[$period] ?? 0.0) + $invoice->total;
        }

        ksort($spend);
        $unitPrices = [];

        foreach ($spend as $period => $total) {
            if ($quantities[$period] > 0) {
                $unitPrices[$period] = $total / $quantities[$period];
            }
        }

        $baselineQuantities = array_intersect_key($quantities, array_flip($baselinePeriods));

        if ($baselineQuantities === [] || $unitPrices === []) {
            return null;
        }

        $latestUnitPrice = end($unitPrices);
        $averageQuantity = array_sum($baselineQuantities) / count($baselinePeriods);
        $driver = $costLine->priceDriver;
        $estimate = null;
        $pending = 0.0;

        if ($driver !== null) {
            $series = $driverSeries[$driver->code] ?? [];
            $estimate = $this->estimator->estimate($unitPrices, $series, $driver->kind);
            $pending = $this->pendingChange($series, $asOf, $estimate->lagMonths);
        }

        return new CostLineProfile(
            costLine: $costLine,
            currentMonthlySpend: $averageQuantity * $latestUnitPrice,
            latestUnitPrice: $latestUnitPrice,
            averageMonthlyQuantity: $averageQuantity,
            unitPrices: $unitPrices,
            monthlySpend: $spend,
            estimate: $estimate,
            pendingDriverChange: $pending,
        );
    }

    /**
     * How much the driver has already moved over the lag window, which has not yet reached the cost line.
     *
     * @param  array<string, float>  $series
     */
    private function pendingChange(array $series, string $asOf, int $lagMonths): float
    {
        if ($lagMonths === 0) {
            return 0.0;
        }

        $now = MonthlySeries::valueAtOrBefore($series, $asOf);
        $then = MonthlySeries::valueAtOrBefore($series, MonthlySeries::shift($asOf, -$lagMonths));

        return $now !== null && $then !== null && $then > 0 ? $now / $then - 1 : 0.0;
    }

    /**
     * Average revenue over the baseline months, restated at today's selling prices.
     *
     * @param  array<string, float>  $revenue
     * @param  array<string, float>  $sellingPriceIndex
     * @param  list<string>  $baselinePeriods
     */
    private function currentRevenue(array $revenue, array $sellingPriceIndex, array $baselinePeriods): float
    {
        $latestIndex = MonthlySeries::valueAtOrBefore($sellingPriceIndex, end($baselinePeriods)) ?? 100.0;
        $restated = [];

        foreach ($baselinePeriods as $period) {
            if (isset($revenue[$period])) {
                $restated[] = $revenue[$period] * $latestIndex / ($sellingPriceIndex[$period] ?? $latestIndex);
            }
        }

        return $restated === [] ? 0.0 : array_sum($restated) / count($restated);
    }

    /**
     * Borrowed projections where they exist; otherwise the driver's own recent trend and volatility.
     *
     * @param  array<string, PriceDriver>  $drivers
     * @param  array<string, array<string, float>>  $driverSeries
     * @return array<string, DriverOutlook>
     */
    private function outlooks(array $drivers, array $driverSeries): array
    {
        $outlooks = [];

        foreach ($drivers as $code => $driver) {
            /** @var DriverProjection|null $projection */
            $projection = $driver->projections->first();

            if ($projection !== null) {
                $outlooks[$code] = DriverOutlook::fromProjection(
                    $projection->change_low,
                    $projection->change_mid,
                    $projection->change_high,
                    $projection->horizon_months,
                    $projection->source,
                );

                continue;
            }

            $changes = array_slice(array_values(MonthlySeries::logChanges($driverSeries[$code] ?? [])), -12);

            if (count($changes) >= 3) {
                $mean = array_sum($changes) / count($changes);
                $variance = array_sum(array_map(fn (float $change): float => ($change - $mean) ** 2, $changes)) / (count($changes) - 1);
                $outlooks[$code] = new DriverOutlook(exp($mean) - 1, max(0.001, sqrt($variance)), 'Trailing 12-month trend of '.$driver->source);
            }
        }

        return $outlooks;
    }
}
