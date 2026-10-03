<?php

namespace App\Services\Margin;

use App\Models\CostLine;
use App\Services\Margin\Simulation\SimulatedLine;

/**
 * A cost line together with its price history and the learned link to its driver.
 */
final readonly class CostLineProfile
{
    /**
     * @param  array<string, float>  $unitPrices  Average unit price per month.
     * @param  array<string, float>  $monthlySpend  Total spend per month.
     */
    public function __construct(
        public CostLine $costLine,
        public float $currentMonthlySpend,
        public float $latestUnitPrice,
        public float $averageMonthlyQuantity,
        public array $unitPrices,
        public array $monthlySpend,
        public ?PassThroughEstimate $estimate,
        public float $pendingDriverChange,
    ) {}

    public function driverCode(): ?string
    {
        return $this->costLine->priceDriver?->code;
    }

    public function toSimulatedLine(): SimulatedLine
    {
        return new SimulatedLine(
            id: $this->costLine->id,
            name: $this->costLine->name,
            monthlySpend: $this->currentMonthlySpend,
            driverCode: $this->driverCode(),
            passThrough: $this->estimate === null ? 0.0 : $this->estimate->passThrough,
            lagMonths: $this->estimate === null ? 0 : $this->estimate->lagMonths,
            pendingDriverChange: $this->pendingDriverChange,
            scalesWithVolume: $this->costLine->scales_with_volume,
        );
    }
}
