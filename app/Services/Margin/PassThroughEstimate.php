<?php

namespace App\Services\Margin;

use App\Enums\Confidence;
use App\Enums\StatementLabel;

/**
 * How strongly (pass-through) and how quickly (lag) a driver moves one cost line.
 */
final readonly class PassThroughEstimate
{
    public const string SourceCompanyHistory = 'company_history';

    public const string SourceIndustryDefault = 'industry_default';

    public function __construct(
        public float $passThrough,
        public int $lagMonths,
        public string $source,
        public Confidence $confidence,
        public ?float $rSquared = null,
        public int $observations = 0,
    ) {}

    public function label(): StatementLabel
    {
        return $this->source === self::SourceCompanyHistory ? StatementLabel::Data : StatementLabel::Assumption;
    }

    /**
     * @return array{pass_through: float, lag_months: int, source: string, confidence: string, label: string, r_squared: float|null, observations: int}
     */
    public function toArray(): array
    {
        return [
            'pass_through' => round($this->passThrough, 2),
            'lag_months' => $this->lagMonths,
            'source' => $this->source,
            'confidence' => $this->confidence->value,
            'label' => $this->label()->value,
            'r_squared' => $this->rSquared === null ? null : round($this->rSquared, 2),
            'observations' => $this->observations,
        ];
    }
}
