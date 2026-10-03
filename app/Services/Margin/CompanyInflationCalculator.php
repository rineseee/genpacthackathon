<?php

namespace App\Services\Margin;

/**
 * Company-specific inflation: each cost line's year-over-year price change, weighted by
 * what the company spends on it today, compared with how much its own selling prices rose.
 */
final class CompanyInflationCalculator
{
    private const int WindowMonths = 3;

    public function calculate(CompanyProfile $profile): CompanyInflation
    {
        $recent = MonthlySeries::window($profile->asOf, self::WindowMonths);
        $yearAgo = array_map(fn (string $period): string => MonthlySeries::shift($period, -12), $recent);

        $lineInflation = [];
        $weightedChange = 0.0;
        $weights = 0.0;
        $extraCosts = 0.0;

        foreach ($profile->lines as $line) {
            $change = $this->change($line->unitPrices, $recent, $yearAgo);

            if ($change === null) {
                continue;
            }

            $lineInflation[$line->costLine->id] = $change;
            $weightedChange += $change * $line->currentMonthlySpend;
            $weights += $line->currentMonthlySpend;
            $extraCosts += $line->currentMonthlySpend * $change / (1 + $change);
        }

        $sellingPriceGrowth = $this->change($profile->sellingPriceIndex, $recent, $yearAgo);
        $extraRevenue = $sellingPriceGrowth === null ? 0.0 : $profile->currentMonthlyRevenue * $sellingPriceGrowth / (1 + $sellingPriceGrowth);
        [$headlineCpi, $headlinePeriod] = $this->headlineCpi($profile);

        return new CompanyInflation(
            costInflation: $weights > 0 ? $weightedChange / $weights : null,
            sellingPriceGrowth: $sellingPriceGrowth,
            headlineCpi: $headlineCpi,
            headlineCpiPeriod: $headlinePeriod,
            profitLostThisMonth: $extraCosts - $extraRevenue,
            extraCostsThisMonth: $extraCosts,
            extraRevenueThisMonth: $extraRevenue,
            lineInflation: $lineInflation,
        );
    }

    /**
     * @param  array<string, float>  $series
     * @param  list<string>  $recent
     * @param  list<string>  $yearAgo
     */
    private function change(array $series, array $recent, array $yearAgo): ?float
    {
        $recentValues = array_intersect_key($series, array_flip($recent));
        $yearAgoValues = array_intersect_key($series, array_flip($yearAgo));

        if ($recentValues === [] || $yearAgoValues === []) {
            return null;
        }

        $before = array_sum($yearAgoValues) / count($yearAgoValues);

        return $before > 0 ? (array_sum($recentValues) / count($recentValues)) / $before - 1 : null;
    }

    /**
     * Latest published year-over-year headline CPI.
     *
     * @return array{0: float|null, 1: string|null}
     */
    private function headlineCpi(CompanyProfile $profile): array
    {
        $series = $profile->driverSeries['cpi.headline'] ?? [];

        if ($series === []) {
            return [null, null];
        }

        $latestPeriod = array_key_last($series);
        $yearAgo = $series[MonthlySeries::shift($latestPeriod, -12)] ?? null;

        return $yearAgo ? [$series[$latestPeriod] / $yearAgo - 1, $latestPeriod] : [null, $latestPeriod];
    }
}
