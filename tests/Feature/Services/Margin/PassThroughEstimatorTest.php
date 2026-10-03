<?php

namespace Tests\Feature\Services\Margin;

use App\Enums\Confidence;
use App\Enums\DriverKind;
use App\Services\Margin\MonthlySeries;
use App\Services\Margin\PassThroughEstimate;
use App\Services\Margin\PassThroughEstimator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class PassThroughEstimatorTest extends TestCase
{
    public function test_learns_pass_through_and_lag_from_company_history(): void
    {
        $changes = [0.03, -0.02, 0.05, 0.01, -0.04, 0.06, 0.02, -0.01, 0.04, -0.03, 0.05, 0.00, 0.03, -0.02, 0.04, 0.01, -0.03, 0.02];
        [$driver, $prices] = $this->series($changes, passThrough: 0.7, lag: 2);

        $estimate = (new PassThroughEstimator)->estimate($prices, $driver, DriverKind::Commodity);

        $this->assertSame(PassThroughEstimate::SourceCompanyHistory, $estimate->source);
        $this->assertSame(2, $estimate->lagMonths);
        $this->assertEqualsWithDelta(0.7, $estimate->passThrough, 0.02);
        $this->assertSame(Confidence::High, $estimate->confidence);
    }

    public function test_falls_back_to_the_industry_default_when_history_is_thin(): void
    {
        [$driver, $prices] = $this->series([0.03, -0.02, 0.05, 0.01], passThrough: 0.7, lag: 0);

        $estimate = (new PassThroughEstimator)->estimate($prices, $driver, DriverKind::Energy);

        $this->assertSame(PassThroughEstimate::SourceIndustryDefault, $estimate->source);
        $this->assertSame(config('margin.pass_through.defaults.energy.pass_through'), $estimate->passThrough);
        $this->assertSame(Confidence::Low, $estimate->confidence);
    }

    /**
     * A driver series built from monthly changes, and a cost line that follows it with a pass-through and lag.
     *
     * @param  list<float>  $changes
     * @return array{0: array<string, float>, 1: array<string, float>}
     */
    private function series(array $changes, float $passThrough, int $lag): array
    {
        $month = CarbonImmutable::create(2025, 1);
        $driver = [MonthlySeries::key($month) => 100.0];
        $value = 100.0;

        foreach ($changes as $index => $change) {
            $value *= exp($change);
            $driver[MonthlySeries::key($month->addMonths($index + 1))] = $value;
        }

        $prices = [];
        foreach (array_keys($driver) as $period) {
            $driverValue = $driver[MonthlySeries::shift($period, -$lag)] ?? 100.0;
            $prices[$period] = 2.0 * exp($passThrough * log($driverValue / 100));
        }

        return [$driver, $prices];
    }
}
