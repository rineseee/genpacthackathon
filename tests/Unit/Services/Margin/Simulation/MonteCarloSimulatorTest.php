<?php

namespace Tests\Unit\Services\Margin\Simulation;

use App\Services\Margin\Simulation\Actions\BuyAhead;
use App\Services\Margin\Simulation\Actions\FixedPriceContract;
use App\Services\Margin\Simulation\Actions\RaisePrices;
use App\Services\Margin\Simulation\Actions\SwitchSupplier;
use App\Services\Margin\Simulation\DriverOutlook;
use App\Services\Margin\Simulation\MonteCarloSimulator;
use App\Services\Margin\Simulation\SimulatedLine;
use App\Services\Margin\Simulation\SimulationInput;
use PHPUnit\Framework\TestCase;

class MonteCarloSimulatorTest extends TestCase
{
    public function test_same_seed_produces_identical_results(): void
    {
        $input = $this->input(volatility: 0.05);

        $this->assertEquals((new MonteCarloSimulator)->run($input), (new MonteCarloSimulator)->run($input));
    }

    public function test_without_uncertainty_profit_follows_pass_through_of_the_driver_drift(): void
    {
        $result = (new MonteCarloSimulator)->run($this->input(drift: 0.10, volatility: 0.0, volumeVolatility: 0.0, months: 1));

        // Revenue 10,000 minus flour 4,000 at +5% (half of the 10% driver move) minus fixed rent 2,000.
        $this->assertEqualsWithDelta(3800.0, $result->finalMonth()['p50'], 0.01);
    }

    public function test_driver_moves_reach_the_cost_line_only_after_its_lag(): void
    {
        $result = (new MonteCarloSimulator)->run($this->input(drift: 0.20, volatility: 0.0, volumeVolatility: 0.0, months: 2, lagMonths: 1));

        $this->assertEqualsWithDelta(4000.0, $result->monthlyProfit[0]['p50'], 0.01);
        $this->assertEqualsWithDelta(3800.0, $result->monthlyProfit[1]['p50'], 0.01);
    }

    public function test_price_rise_lifts_revenue_net_of_the_assumed_volume_loss(): void
    {
        $input = $this->input(drift: 0.0, volatility: 0.0, volumeVolatility: 0.0, months: 1);

        $result = (new MonteCarloSimulator)->run($input->withActions([new RaisePrices([1 => 0.10])]));

        // Volume falls 5% (elasticity -0.5): revenue 10,000 x 1.10 x 0.95, flour scales with volume, rent does not.
        $this->assertEqualsWithDelta(10450 - 4000 * 0.95 - 2000, $result->finalMonth()['p50'], 0.01);
    }

    public function test_supplier_switch_and_fixed_price_change_only_their_own_line(): void
    {
        $input = $this->input(drift: 0.10, volatility: 0.0, volumeVolatility: 0.0, months: 1);

        $switched = (new MonteCarloSimulator)->run($input->withActions([new SwitchSupplier(1, 0.5, 0.9)]));
        $fixed = (new MonteCarloSimulator)->run($input->withActions([new FixedPriceContract(1, 1.02, 6)]));

        $this->assertEqualsWithDelta(10000 - 4000 * 1.05 * 0.95 - 2000, $switched->finalMonth()['p50'], 0.01);
        $this->assertEqualsWithDelta(10000 - 4000 * 1.02 - 2000, $fixed->finalMonth()['p50'], 0.01);
    }

    public function test_buying_ahead_moves_the_cash_outflow_to_the_first_month(): void
    {
        $input = $this->input(drift: 0.0, volatility: 0.0, volumeVolatility: 0.0, months: 3, startingCash: 10000, reserve: 5000);

        $result = (new MonteCarloSimulator)->run($input->withActions([new BuyAhead(1, 3, 0.0)]));

        // Month 1 prepays two more months of flour (8,000), pushing cash from 10,000 + 4,000 profit to 6,000 then back up.
        $this->assertSame(0.0, $result->stressProbability);
        $this->assertEqualsWithDelta(10000 + 3 * 4000, $result->endingCash['p50'], 0.01);
    }

    public function test_stress_probability_counts_paths_where_cash_falls_below_the_reserve(): void
    {
        $result = (new MonteCarloSimulator)->run($this->input(drift: 0.0, volatility: 0.0, volumeVolatility: 0.0, startingCash: 1000, reserve: 50000));

        $this->assertSame(1.0, $result->stressProbability);
    }

    private function input(
        float $drift = 0.02,
        float $volatility = 0.03,
        float $volumeVolatility = 0.03,
        int $months = 6,
        int $lagMonths = 0,
        float $startingCash = 50000,
        float $reserve = 0,
    ): SimulationInput {
        return new SimulationInput(
            monthlyRevenue: 10000,
            lines: [
                new SimulatedLine(1, 'Flour', 4000, 'commodity.wheat', 0.5, $lagMonths, 0.0, true),
                new SimulatedLine(2, 'Rent', 2000, null, 0.0, 0, 0.0, false),
            ],
            drivers: ['commodity.wheat' => new DriverOutlook($drift / $months, $volatility, 'test')],
            priceElasticity: -0.5,
            startingCash: $startingCash,
            minimumCashReserve: $reserve,
            monthlyNonOperatingOutflows: 0,
            months: $months,
            paths: 200,
            seed: 7,
            driverCorrelation: 0.5,
            volumeVolatility: $volumeVolatility,
        );
    }
}
