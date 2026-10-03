<?php

namespace App\Services\Margin;

use App\Models\Company;
use App\Models\PriceDriver;
use App\Services\Margin\Simulation\DriverOutlook;

/**
 * Snapshot of one company as the engine sees it: today's cost base at today's prices,
 * its history, and the outlook for every price driver its costs follow.
 */
final readonly class CompanyProfile
{
    /**
     * @param  list<CostLineProfile>  $lines
     * @param  array<string, float>  $revenue  Revenue per month.
     * @param  array<string, float>  $sellingPriceIndex  Own selling-price index per month.
     * @param  array<string, PriceDriver>  $drivers
     * @param  array<string, array<string, float>>  $driverSeries  Observations per driver code, keyed by month.
     * @param  array<string, DriverOutlook>  $driverOutlooks
     */
    public function __construct(
        public Company $company,
        public string $asOf,
        public float $currentMonthlyRevenue,
        public array $lines,
        public array $revenue,
        public array $sellingPriceIndex,
        public array $drivers,
        public array $driverSeries,
        public array $driverOutlooks,
    ) {}

    public function currentMonthlyCosts(): float
    {
        return array_sum(array_map(fn (CostLineProfile $line): float => $line->currentMonthlySpend, $this->lines));
    }

    public function currentMonthlyProfit(): float
    {
        return $this->currentMonthlyRevenue - $this->currentMonthlyCosts();
    }

    public function line(int $costLineId): ?CostLineProfile
    {
        foreach ($this->lines as $line) {
            if ($line->costLine->id === $costLineId) {
                return $line;
            }
        }

        return null;
    }

    /**
     * Costs that move with sales volume, as a share of revenue.
     */
    public function variableCostRatio(): float
    {
        $variableCosts = array_sum(array_map(
            fn (CostLineProfile $line): float => $line->costLine->scales_with_volume ? $line->currentMonthlySpend : 0.0,
            $this->lines,
        ));

        return $this->currentMonthlyRevenue > 0 ? $variableCosts / $this->currentMonthlyRevenue : 0.0;
    }
}
