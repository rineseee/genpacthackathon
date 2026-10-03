<?php

namespace App\Services\Margin;

use App\Enums\Confidence;
use App\Enums\DriverKind;

/**
 * Learns the link between a public price driver and one company cost line from the
 * company's own invoices: a regression of monthly price changes on lagged driver changes.
 * Falls back to industry defaults when history is too thin or too noisy.
 */
final class PassThroughEstimator
{
    /**
     * @param  array<string, float>  $linePrices  Monthly unit prices keyed by period.
     * @param  array<string, float>  $driverValues  Monthly driver values keyed by period.
     */
    public function estimate(array $linePrices, array $driverValues, DriverKind $driverKind): PassThroughEstimate
    {
        $lineChanges = MonthlySeries::logChanges($linePrices);
        $driverChanges = MonthlySeries::logChanges($driverValues);
        $best = null;

        for ($lag = 0; $lag <= (int) config('margin.pass_through.max_lag_months'); $lag++) {
            $pairs = [];

            foreach ($lineChanges as $period => $lineChange) {
                $driverChange = $driverChanges[MonthlySeries::shift($period, -$lag)] ?? null;

                if ($driverChange !== null) {
                    $pairs[] = [$driverChange, $lineChange];
                }
            }

            $fit = $this->fit($pairs);

            if ($fit !== null && $fit['slope'] > 0 && ($best === null || $fit['r_squared'] > $best['r_squared'])) {
                $best = $fit + ['lag' => $lag];
            }
        }

        if ($best === null || $best['r_squared'] < (float) config('margin.pass_through.min_r_squared')) {
            return $this->industryDefault($driverKind, $best === null ? 0 : $best['observations']);
        }

        return new PassThroughEstimate(
            passThrough: min($best['slope'], 1.5),
            lagMonths: $best['lag'],
            source: PassThroughEstimate::SourceCompanyHistory,
            confidence: $best['r_squared'] >= 0.6 && $best['observations'] >= 12 ? Confidence::High : Confidence::Medium,
            rSquared: $best['r_squared'],
            observations: $best['observations'],
        );
    }

    public function industryDefault(DriverKind $driverKind, int $observations = 0): PassThroughEstimate
    {
        $default = config('margin.pass_through.defaults.'.$driverKind->value);

        return new PassThroughEstimate(
            passThrough: (float) $default['pass_through'],
            lagMonths: (int) $default['lag_months'],
            source: PassThroughEstimate::SourceIndustryDefault,
            confidence: Confidence::Low,
            observations: $observations,
        );
    }

    /**
     * Ordinary least squares with an intercept.
     *
     * @param  list<array{0: float, 1: float}>  $pairs
     * @return array{slope: float, r_squared: float, observations: int}|null
     */
    private function fit(array $pairs): ?array
    {
        $count = count($pairs);

        if ($count < (int) config('margin.pass_through.min_observations')) {
            return null;
        }

        $meanX = array_sum(array_column($pairs, 0)) / $count;
        $meanY = array_sum(array_column($pairs, 1)) / $count;
        $covariance = 0.0;
        $varianceX = 0.0;
        $varianceY = 0.0;

        foreach ($pairs as [$x, $y]) {
            $covariance += ($x - $meanX) * ($y - $meanY);
            $varianceX += ($x - $meanX) ** 2;
            $varianceY += ($y - $meanY) ** 2;
        }

        if ($varianceX <= 0.0 || $varianceY <= 0.0) {
            return null;
        }

        return [
            'slope' => $covariance / $varianceX,
            'r_squared' => ($covariance ** 2) / ($varianceX * $varianceY),
            'observations' => $count,
        ];
    }
}
