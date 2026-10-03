<?php

namespace App\Services\Margin\Simulation\Actions;

use App\Services\Margin\Simulation\SimulatedLine;

/**
 * Lock a cost line at a fixed price for a number of months, removing market exposure.
 */
final readonly class FixedPriceContract implements SimulationAction
{
    /**
     * @param  float  $priceRatio  Fixed unit price divided by today's unit price.
     */
    public function __construct(
        public int $lineId,
        public float $priceRatio,
        public int $durationMonths,
        public ?int $offerId = null,
        public ?string $supplierName = null,
    ) {}

    public function sellingPriceMultiplier(int $month): float
    {
        return 1.0;
    }

    public function lineCostMultiplier(SimulatedLine $line, int $month, float $multiplier): float
    {
        if ($line->id !== $this->lineId || $month > $this->durationMonths) {
            return $multiplier;
        }

        return $this->priceRatio;
    }

    public function extraCashOutflow(SimulatedLine $line, int $month, float $lineCost): float
    {
        return 0.0;
    }

    public function costLineId(): ?int
    {
        return $this->lineId;
    }

    public function toArray(): array
    {
        return [
            'type' => 'fixed_price',
            'cost_line_id' => $this->lineId,
            'offer_id' => $this->offerId,
            'supplier' => $this->supplierName,
            'duration_months' => $this->durationMonths,
            'price_difference_percent' => round(($this->priceRatio - 1) * 100, 1),
        ];
    }
}
