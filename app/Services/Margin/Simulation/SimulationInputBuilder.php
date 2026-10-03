<?php

namespace App\Services\Margin\Simulation;

use App\Enums\SimulationScenario;
use App\Services\Margin\CompanyProfile;
use App\Services\Margin\CostLineProfile;
use App\Services\Margin\Simulation\Actions\SimulationAction;

/**
 * Turns a company profile and a scenario into the pure input the simulator runs on.
 */
final class SimulationInputBuilder
{
    /**
     * @param  array<string, float>  $shocks  One-off driver jumps in month 1, as fractions keyed by driver code.
     * @param  list<SimulationAction>  $actions
     */
    public function build(
        CompanyProfile $profile,
        SimulationScenario $scenario = SimulationScenario::Baseline,
        array $shocks = [],
        array $actions = [],
        ?int $paths = null,
        ?int $seed = null,
    ): SimulationInput {
        $company = $profile->company;

        return new SimulationInput(
            monthlyRevenue: $profile->currentMonthlyRevenue,
            lines: array_map(fn (CostLineProfile $line): SimulatedLine => $line->toSimulatedLine(), $profile->lines),
            drivers: $this->drivers($profile, $scenario),
            priceElasticity: $company->price_elasticity,
            startingCash: $company->cash_balance,
            minimumCashReserve: $company->minimum_cash_reserve,
            monthlyNonOperatingOutflows: $company->monthly_non_operating_outflows,
            months: (int) config('margin.simulation.horizon_months'),
            paths: $paths ?? (int) config('margin.simulation.paths'),
            seed: $seed ?? (int) config('margin.simulation.seed'),
            driverCorrelation: (float) config('margin.simulation.driver_correlation'),
            volumeVolatility: (float) config('margin.simulation.volume_volatility'),
            shocks: array_intersect_key($shocks, $profile->driverOutlooks),
            actions: $actions,
        );
    }

    /**
     * @return array<string, DriverOutlook>
     */
    private function drivers(CompanyProfile $profile, SimulationScenario $scenario): array
    {
        if ($scenario !== SimulationScenario::Replay2022) {
            return $profile->driverOutlooks;
        }

        $replay = config('margin.scenarios.replay_2022.monthly_changes');
        $drivers = [];

        foreach ($profile->driverOutlooks as $code => $outlook) {
            $drivers[$code] = isset($replay[$code]) ? $outlook->withScenario($replay[$code]) : $outlook;
        }

        return $drivers;
    }
}
