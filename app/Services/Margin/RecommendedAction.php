<?php

namespace App\Services\Margin;

use App\Enums\StatementLabel;
use App\Services\Margin\Simulation\Actions\SimulationAction;
use App\Services\Margin\Simulation\SimulationResult;

/**
 * One response the owner could take, simulated against doing nothing on the same futures.
 */
final readonly class RecommendedAction
{
    /**
     * @param  array<string, LabelledValue>  $metrics  Extra honest metrics (break-even volume loss, months covered...).
     */
    public function __construct(
        public SimulationAction $action,
        public string $title,
        public SimulationResult $result,
        public float $profitGain,
        public float $quarterProfitGain,
        public float $finalMonthProfitGain,
        public float $stressProbabilityChange,
        public array $metrics = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action->toArray(),
            'title' => $this->title,
            'label' => StatementLabel::AiSuggestion->value,
            'expected_profit_gain' => LabelledValue::euros($this->profitGain, StatementLabel::Forecast),
            'expected_profit_gain_next_quarter' => LabelledValue::euros($this->quarterProfitGain, StatementLabel::Forecast),
            'monthly_profit_gain_at_horizon' => LabelledValue::euros($this->finalMonthProfitGain, StatementLabel::Forecast),
            'stress_probability_with_action' => LabelledValue::percent($this->result->stressProbability, StatementLabel::Forecast),
            'stress_probability_change' => LabelledValue::percent($this->stressProbabilityChange, StatementLabel::Forecast),
            'metrics' => $this->metrics,
        ];
    }
}
