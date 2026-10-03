<?php

namespace App\Services\Margin;

use Carbon\CarbonImmutable;

/**
 * Helpers for monthly series keyed by period ("2026-09").
 */
final class MonthlySeries
{
    public static function key(CarbonImmutable $month): string
    {
        return $month->format('Y-m');
    }

    public static function shift(string $period, int $months): string
    {
        return self::key(CarbonImmutable::createFromFormat('!Y-m', $period)->addMonthsNoOverflow($months));
    }

    /**
     * Month-over-month log changes, keyed by the later period. Gaps break the chain.
     *
     * @param  array<string, float>  $series
     * @return array<string, float>
     */
    public static function logChanges(array $series): array
    {
        $changes = [];

        foreach ($series as $period => $value) {
            $previous = $series[self::shift($period, -1)] ?? null;

            if ($previous !== null && $previous > 0 && $value > 0) {
                $changes[$period] = log($value / $previous);
            }
        }

        return $changes;
    }

    /**
     * The value at a period, or the latest value before it when the period is missing.
     *
     * @param  array<string, float>  $series
     */
    public static function valueAtOrBefore(array $series, string $period): ?float
    {
        if (isset($series[$period])) {
            return $series[$period];
        }

        $candidates = array_filter($series, fn (string $key): bool => $key <= $period, ARRAY_FILTER_USE_KEY);

        if ($candidates === []) {
            return null;
        }

        ksort($candidates);

        return end($candidates);
    }

    /**
     * Average of the values at the given periods (falling back to the latest earlier value).
     *
     * @param  array<string, float>  $series
     * @param  list<string>  $periods
     */
    public static function averageAt(array $series, array $periods): ?float
    {
        $values = array_filter(array_map(fn (string $period): ?float => self::valueAtOrBefore($series, $period), $periods), fn (?float $value): bool => $value !== null);

        return $values === [] ? null : array_sum($values) / count($values);
    }

    /**
     * The last N period keys ending at (and including) the given period.
     *
     * @return list<string>
     */
    public static function window(string $endPeriod, int $months): array
    {
        $periods = [];

        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $periods[] = self::shift($endPeriod, -$offset);
        }

        return $periods;
    }

    /**
     * Linear-interpolated percentile of an unsorted list.
     *
     * @param  list<float>  $values
     */
    public static function percentile(array $values, float $percentile): float
    {
        sort($values);

        return self::sortedPercentile($values, $percentile);
    }

    /**
     * @param  list<float>  $sortedValues
     */
    public static function sortedPercentile(array $sortedValues, float $percentile): float
    {
        $count = count($sortedValues);

        if ($count === 0) {
            return 0.0;
        }

        $position = ($count - 1) * $percentile;
        $lower = (int) floor($position);
        $upper = (int) ceil($position);

        return $sortedValues[$lower] + ($sortedValues[$upper] - $sortedValues[$lower]) * ($position - $lower);
    }
}
