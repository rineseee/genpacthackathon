<?php

namespace App\Services\Margin\Simulation;

/**
 * Where a price driver is expected to go: a monthly drift, a monthly volatility and,
 * for replayed scenarios, an explicit month-by-month path that replaces the drift.
 */
final readonly class DriverOutlook
{
    /**
     * @param  list<float>|null  $scenarioChanges  Month-by-month changes that replace the drift (index 0 is month 1).
     */
    public function __construct(
        public float $monthlyDrift,
        public float $monthlyVolatility,
        public string $source,
        public ?array $scenarioChanges = null,
    ) {}

    public function driftForMonth(int $month): float
    {
        return $this->scenarioChanges[$month - 1] ?? $this->monthlyDrift;
    }

    /**
     * Build an outlook from a projected cumulative change over a horizon, given as a low / mid / high range
     * where low and high are read as the 10th and 90th percentiles.
     */
    public static function fromProjection(float $low, float $mid, float $high, int $horizonMonths, string $source): self
    {
        $drift = (1 + $mid) ** (1 / $horizonMonths) - 1;
        $spread = (log(1 + $high) - log(1 + $low)) / (2 * 1.2816);

        return new self($drift, max(0.001, $spread / sqrt($horizonMonths)), $source);
    }

    public function withScenario(array $scenarioChanges): self
    {
        return new self($this->monthlyDrift, $this->monthlyVolatility, $this->source, $scenarioChanges);
    }
}
