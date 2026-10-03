<?php

namespace App\Services\Margin\Simulation\Actions;

use App\Services\Margin\Simulation\SimulatedLine;

/**
 * Buy several months of a storable cost line now, at today's price plus storage cost, paying up front.
 */
final readonly class BuyAhead implements SimulationAction
{
    public function __construct(
        public int $lineId,
        public int $months,
        public float $monthlyStorageCostRate,
    ) {}

    public function sellingPriceMultiplier(int $month): float
    {
        return 1.0;
    }

    public function lineCostMultiplier(SimulatedLine $line, int $month, float $multiplier): float
    {
        if ($line->id !== $this->lineId || $month > $this->months) {
            return $multiplier;
        }

        return $this->stockedPriceMultiplier($month);
    }

    /**
     * Month 1 pays for the whole stock; later stocked months are already paid for.
     */
    public function extraCashOutflow(SimulatedLine $line, int $month, float $lineCost): float
    {
        if ($line->id !== $this->lineId || $month > $this->months) {
            return 0.0;
        }

        if ($month === 1) {
            $prepaid = 0.0;

            for ($stockedMonth = 2; $stockedMonth <= $this->months; $stockedMonth++) {
                $prepaid += $line->monthlySpend * $this->stockedPriceMultiplier($stockedMonth);
            }

            return $prepaid;
        }

        return -$lineCost;
    }

    public function costLineId(): ?int
    {
        return $this->lineId;
    }

    /**
     * Today's price plus the storage cost of holding the stock until the given month.
     */
    private function stockedPriceMultiplier(int $month): float
    {
        return 1 + $this->monthlyStorageCostRate * ($month - 1);
    }

    public function toArray(): array
    {
        return [
            'type' => 'buy_ahead',
            'cost_line_id' => $this->lineId,
            'months' => $this->months,
            'monthly_storage_cost_percent' => round($this->monthlyStorageCostRate * 100, 2),
        ];
    }
}
