<?php

namespace App\Services\Margin;

use App\Enums\StatementLabel;
use App\Models\PriceDriver;
use App\Models\Supplier;

/**
 * A supplier whose price for one cost line rose faster than its market explains.
 */
final readonly class SupplierFlag
{
    /**
     * @param  array{en: string, sq: string}  $renegotiationDraft
     */
    public function __construct(
        public Supplier $supplier,
        public CostLineProfile $line,
        public string $fromPeriod,
        public string $toPeriod,
        public float $supplierChange,
        public float $marketChange,
        public float $expectedChange,
        public float $excessChange,
        public float $monthlyOvercharge,
        public float $monthlyQuantity,
        public PriceDriver $benchmark,
        public bool $benchmarkIsFallback,
        public array $renegotiationDraft,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'supplier' => ['id' => $this->supplier->id, 'name' => $this->supplier->name],
            'cost_line' => ['id' => $this->line->costLine->id, 'name' => $this->line->costLine->name],
            'driver' => $this->line->costLine->priceDriver?->only(['code', 'name']),
            'benchmark' => [
                ...$this->benchmark->only(['code', 'name', 'source']),
                'is_fallback' => $this->benchmarkIsFallback,
                'reason' => $this->benchmarkIsFallback ? 'The official data for this cost line is not yet published for these months; compared with headline inflation instead.' : null,
            ],
            'period' => ['from' => $this->fromPeriod, 'to' => $this->toPeriod],
            'supplier_price_change' => LabelledValue::percent($this->supplierChange, StatementLabel::Data),
            'market_change' => LabelledValue::percent($this->marketChange, StatementLabel::Data, source: $this->benchmark->source),
            'expected_change' => LabelledValue::percent($this->expectedChange, $this->benchmarkIsFallback ? StatementLabel::Assumption : ($this->line->estimate?->label() ?? StatementLabel::Assumption)),
            'excess_change' => LabelledValue::percent($this->excessChange, StatementLabel::Data),
            'monthly_overcharge' => LabelledValue::euros($this->monthlyOvercharge, StatementLabel::Data),
            'renegotiation_draft' => [
                'label' => StatementLabel::AiSuggestion->value,
                'needs_validation' => true,
                'messages' => $this->renegotiationDraft,
            ],
        ];
    }
}
