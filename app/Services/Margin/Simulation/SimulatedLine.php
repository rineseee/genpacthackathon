<?php

namespace App\Services\Margin\Simulation;

/**
 * One cost line as the simulator sees it: today's monthly spend and how a driver moves it.
 */
final readonly class SimulatedLine
{
    /**
     * @param  float  $pendingDriverChange  Driver change already observed but not yet passed into this line's price (because of the lag).
     */
    public function __construct(
        public int $id,
        public string $name,
        public float $monthlySpend,
        public ?string $driverCode,
        public float $passThrough,
        public int $lagMonths,
        public float $pendingDriverChange,
        public bool $scalesWithVolume,
    ) {}
}
