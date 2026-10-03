<?php

namespace App\Services\Margin;

use App\Enums\Confidence;
use App\Enums\StatementLabel;
use JsonSerializable;

/**
 * A number the engine produced, with its unit, its label (data, forecast, assumption...)
 * and, for forecasts, the range and confidence it must always be shown with.
 */
final readonly class LabelledValue implements JsonSerializable
{
    public function __construct(
        public float $value,
        public string $unit,
        public StatementLabel $label,
        public ?float $low = null,
        public ?float $high = null,
        public ?Confidence $confidence = null,
        public ?string $source = null,
    ) {}

    public static function euros(float $value, StatementLabel $label, ?float $low = null, ?float $high = null, ?Confidence $confidence = null, ?string $source = null): self
    {
        return new self(round($value), 'EUR', $label, $low === null ? null : round($low), $high === null ? null : round($high), $confidence, $source);
    }

    /**
     * Build a percentage value from a fraction (0.073 becomes 7.3).
     */
    public static function percent(float $fraction, StatementLabel $label, ?float $lowFraction = null, ?float $highFraction = null, ?Confidence $confidence = null, ?string $source = null): self
    {
        return new self(
            round($fraction * 100, 1),
            'percent',
            $label,
            $lowFraction === null ? null : round($lowFraction * 100, 1),
            $highFraction === null ? null : round($highFraction * 100, 1),
            $confidence,
            $source,
        );
    }

    /**
     * @return array{value: float, unit: string, label: string, low?: float, high?: float, confidence?: string, source?: string}
     */
    public function jsonSerialize(): array
    {
        return array_filter([
            'value' => $this->value,
            'unit' => $this->unit,
            'label' => $this->label->value,
            'low' => $this->low,
            'high' => $this->high,
            'confidence' => $this->confidence?->value,
            'source' => $this->source,
        ], fn (mixed $value): bool => $value !== null);
    }
}
