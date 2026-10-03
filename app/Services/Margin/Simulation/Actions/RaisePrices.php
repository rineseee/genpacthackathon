<?php

namespace App\Services\Margin\Simulation\Actions;

use App\Services\Margin\Simulation\SimulatedLine;

/**
 * Raise selling prices in one or more steps.
 */
final readonly class RaisePrices implements SimulationAction
{
    /**
     * @param  array<int, float>  $steps  Price increase (as a fraction) keyed by the month it takes effect.
     */
    public function __construct(public array $steps) {}

    public function totalIncrease(): float
    {
        return array_product(array_map(fn (float $step): float => 1 + $step, $this->steps)) - 1;
    }

    public function sellingPriceMultiplier(int $month): float
    {
        $multiplier = 1.0;

        foreach ($this->steps as $stepMonth => $increase) {
            if ($stepMonth <= $month) {
                $multiplier *= 1 + $increase;
            }
        }

        return $multiplier;
    }

    public function lineCostMultiplier(SimulatedLine $line, int $month, float $multiplier): float
    {
        return $multiplier;
    }

    public function extraCashOutflow(SimulatedLine $line, int $month, float $lineCost): float
    {
        return 0.0;
    }

    public function costLineId(): ?int
    {
        return null;
    }

    public function toArray(): array
    {
        return [
            'type' => 'raise_prices',
            'steps' => array_map(
                fn (int $month, float $increase): array => ['month' => $month, 'percent' => round($increase * 100, 2)],
                array_keys($this->steps),
                $this->steps,
            ),
        ];
    }
}
