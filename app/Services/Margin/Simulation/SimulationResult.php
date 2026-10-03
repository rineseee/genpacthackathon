<?php

namespace App\Services\Margin\Simulation;

use App\Enums\StatementLabel;
use App\Services\Margin\LabelledValue;

/**
 * Distribution of outcomes over all simulated futures.
 */
final readonly class SimulationResult
{
    /**
     * @param  list<array{month: int, mean: float, p10: float, p50: float, p90: float}>  $monthlyProfit
     * @param  array{mean: float, p10: float, p50: float, p90: float}  $firstQuarterProfit
     * @param  array{mean: float, p10: float, p50: float, p90: float}  $endingCash
     * @param  array<int, list<array{month: int, p10: float, p50: float, p90: float}>>  $lineMultipliers  Market price multiplier per cost line id and month.
     */
    public function __construct(
        public array $monthlyProfit,
        public float $expectedTotalProfit,
        public array $firstQuarterProfit,
        public array $endingCash,
        public float $stressProbability,
        public array $lineMultipliers,
        public int $paths,
        public int $seed,
    ) {}

    /**
     * @return array{month: int, mean: float, p10: float, p50: float, p90: float}
     */
    public function finalMonth(): array
    {
        return $this->monthlyProfit[array_key_last($this->monthlyProfit)];
    }

    /**
     * The outcome as shown to the owner: always a range, always labelled as a forecast.
     *
     * @return array<string, mixed>
     */
    public function outcome(): array
    {
        $final = $this->finalMonth();

        return [
            'profit_at_horizon' => LabelledValue::euros($final['p50'], StatementLabel::Forecast, $final['p10'], $final['p90']),
            'stress_probability' => LabelledValue::percent($this->stressProbability, StatementLabel::Forecast),
            'ending_cash' => LabelledValue::euros($this->endingCash['p50'], StatementLabel::Forecast, $this->endingCash['p10'], $this->endingCash['p90']),
            'expected_total_profit' => LabelledValue::euros($this->expectedTotalProfit, StatementLabel::Forecast),
            'monthly_profit' => array_map(fn (array $month): array => [
                'month' => $month['month'],
                'p10' => round($month['p10']),
                'p50' => round($month['p50']),
                'p90' => round($month['p90']),
            ], $this->monthlyProfit),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $round = fn (array $values): array => array_map(fn (float|int $value): float|int => is_float($value) ? round($value) : $value, $values);

        return [
            'monthly_profit' => array_map($round, $this->monthlyProfit),
            'expected_total_profit' => round($this->expectedTotalProfit),
            'first_quarter_profit' => $round($this->firstQuarterProfit),
            'ending_cash' => $round($this->endingCash),
            'stress_probability' => round($this->stressProbability, 4),
            'paths' => $this->paths,
            'seed' => $this->seed,
        ];
    }
}
