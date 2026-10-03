<?php

namespace App\Services\Margin\Simulation\Actions;

use App\Services\Margin\Simulation\SimulatedLine;

/**
 * Buy a share of a cost line from a cheaper supplier. The alternative still moves with the market.
 */
final readonly class SwitchSupplier implements SimulationAction
{
    /**
     * @param  float  $priceRatio  Alternative unit price divided by today's unit price.
     */
    public function __construct(
        public int $lineId,
        public float $share,
        public float $priceRatio,
        public ?int $offerId = null,
        public ?string $supplierName = null,
    ) {}

    public function sellingPriceMultiplier(int $month): float
    {
        return 1.0;
    }

    public function lineCostMultiplier(SimulatedLine $line, int $month, float $multiplier): float
    {
        if ($line->id !== $this->lineId) {
            return $multiplier;
        }

        return $multiplier * (1 - $this->share * (1 - $this->priceRatio));
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
            'type' => 'switch_supplier',
            'cost_line_id' => $this->lineId,
            'offer_id' => $this->offerId,
            'supplier' => $this->supplierName,
            'share_percent' => round($this->share * 100, 1),
            'price_difference_percent' => round(($this->priceRatio - 1) * 100, 1),
        ];
    }
}
