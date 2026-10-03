<?php

namespace App\Services\Margin\Simulation;

use App\Services\Margin\Simulation\Actions\SimulationAction;

/**
 * Everything one simulation run needs. Pure data: no database access inside the simulator.
 */
final readonly class SimulationInput
{
    /**
     * @param  list<SimulatedLine>  $lines
     * @param  array<string, DriverOutlook>  $drivers
     * @param  array<string, float>  $shocks  One-off driver jumps applied in month 1 (what-if questions), as fractions.
     * @param  list<SimulationAction>  $actions
     */
    public function __construct(
        public float $monthlyRevenue,
        public array $lines,
        public array $drivers,
        public float $priceElasticity,
        public float $startingCash,
        public float $minimumCashReserve,
        public float $monthlyNonOperatingOutflows,
        public int $months,
        public int $paths,
        public int $seed,
        public float $driverCorrelation,
        public float $volumeVolatility,
        public array $shocks = [],
        public array $actions = [],
    ) {}

    /**
     * @param  list<SimulationAction>  $actions
     */
    public function withActions(array $actions): self
    {
        return new self(
            $this->monthlyRevenue, $this->lines, $this->drivers, $this->priceElasticity, $this->startingCash,
            $this->minimumCashReserve, $this->monthlyNonOperatingOutflows, $this->months, $this->paths, $this->seed,
            $this->driverCorrelation, $this->volumeVolatility, $this->shocks, $actions,
        );
    }

    public function monthlyCosts(): float
    {
        return array_sum(array_map(fn (SimulatedLine $line): float => $line->monthlySpend, $this->lines));
    }

    public function monthlyProfit(): float
    {
        return $this->monthlyRevenue - $this->monthlyCosts();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'monthly_revenue' => round($this->monthlyRevenue, 2),
            'monthly_costs' => round($this->monthlyCosts(), 2),
            'price_elasticity' => $this->priceElasticity,
            'starting_cash' => $this->startingCash,
            'minimum_cash_reserve' => $this->minimumCashReserve,
            'monthly_non_operating_outflows' => $this->monthlyNonOperatingOutflows,
            'months' => $this->months,
            'paths' => $this->paths,
            'seed' => $this->seed,
            'driver_correlation' => $this->driverCorrelation,
            'volume_volatility' => $this->volumeVolatility,
            'shocks' => $this->shocks,
            'actions' => array_map(fn (SimulationAction $action): array => $action->toArray(), $this->actions),
            'lines' => array_map(fn (SimulatedLine $line): array => [
                'id' => $line->id,
                'name' => $line->name,
                'monthly_spend' => round($line->monthlySpend, 2),
                'driver' => $line->driverCode,
                'pass_through' => round($line->passThrough, 3),
                'lag_months' => $line->lagMonths,
                'pending_driver_change' => round($line->pendingDriverChange, 4),
            ], $this->lines),
            'drivers' => array_map(fn (DriverOutlook $outlook): array => [
                'monthly_drift' => round($outlook->monthlyDrift, 5),
                'monthly_volatility' => round($outlook->monthlyVolatility, 5),
                'source' => $outlook->source,
                'scenario_changes' => $outlook->scenarioChanges,
            ], $this->drivers),
        ];
    }
}
