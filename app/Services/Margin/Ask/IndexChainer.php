<?php

namespace App\Services\Margin\Ask;

use App\Services\Margin\MonthlySeries;

/**
 * Builds one continuous monthly index (first month = 100) from published levels and changes.
 *
 * Month-to-month moves come from the published levels while they share a base, and from the
 * published monthly change when ASK rebases the index (the levels jump, the change does not).
 * Where an annual change is published, the month is set from the index twelve months earlier,
 * so the series reproduces the official year-over-year figures exactly.
 */
final class IndexChainer
{
    /** Largest gap (in percentage points) between level ratio and published change that still counts as the same base. */
    private const float SameBaseTolerance = 1.0;

    /**
     * @param  array<string, float|null>  $levels  Index level by period ("2026-03").
     * @param  array<string, float|null>  $monthlyChanges  Month-on-month change in percent.
     * @param  array<string, float|null>  $annualChanges  Year-on-year change in percent.
     * @return array<string, float>
     */
    public function chain(array $levels, array $monthlyChanges, array $annualChanges = []): array
    {
        $published = fn (array $series): array => array_keys(array_filter($series, fn (?float $value): bool => $value !== null));
        $periods = array_unique([...$published($levels), ...$published($monthlyChanges), ...$published($annualChanges)]);
        sort($periods);
        $index = [];

        foreach ($periods as $period) {
            $previous = MonthlySeries::shift($period, -1);
            $ratio = $this->monthlyRatio($levels[$period] ?? null, $levels[$previous] ?? null, $monthlyChanges[$period] ?? null);
            $yearAgo = $index[MonthlySeries::shift($period, -12)] ?? null;

            // The published annual change is the official figure, so it wins wherever it can be applied.
            if (($annualChanges[$period] ?? null) !== null && $yearAgo !== null) {
                $index[$period] = $yearAgo * (1 + $annualChanges[$period] / 100);

                continue;
            }

            if ($index === []) {
                if ($ratio !== null || isset($levels[$period])) {
                    $index[$period] = 100.0;
                }

                continue;
            }

            if ($ratio !== null && isset($index[$previous])) {
                $index[$period] = $index[$previous] * $ratio;
            }
        }

        return array_map(fn (float $value): float => round($value, 4), $index);
    }

    private function monthlyRatio(?float $level, ?float $previousLevel, ?float $monthlyChange): ?float
    {
        if ($level !== null && $previousLevel !== null && $previousLevel > 0) {
            $levelRatio = $level / $previousLevel;

            if ($monthlyChange === null || abs(($levelRatio - 1) * 100 - $monthlyChange) <= self::SameBaseTolerance) {
                return $levelRatio;
            }
        }

        return $monthlyChange === null ? null : 1 + $monthlyChange / 100;
    }
}
