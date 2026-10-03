<?php

namespace App\Services\Margin\Simulation\Actions;

use App\Services\Margin\Simulation\SimulatedLine;

/**
 * A response the owner could take, expressed as changes to the simulated future.
 */
interface SimulationAction
{
    /**
     * Cumulative selling-price multiplier in force during the given month (1.0 = unchanged).
     */
    public function sellingPriceMultiplier(int $month): float;

    /**
     * Adjust a cost line's price multiplier (relative to today's price) for the given month.
     *
     * @param  float  $multiplier  The multiplier after market moves and earlier actions.
     */
    public function lineCostMultiplier(SimulatedLine $line, int $month, float $multiplier): float;

    /**
     * Cash paid out this month beyond the month's profit-and-loss cost (negative when paid earlier).
     */
    public function extraCashOutflow(SimulatedLine $line, int $month, float $lineCost): float;

    /**
     * The cost line this action changes. Line hooks are only called for this line; null means the
     * action changes selling prices only. Two actions on the same line do not combine in a plan.
     */
    public function costLineId(): ?int;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
