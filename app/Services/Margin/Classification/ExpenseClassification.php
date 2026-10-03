<?php

namespace App\Services\Margin\Classification;

use App\Enums\CostCategory;

/**
 * A suggested link from a messy expense description to a cost line and price driver.
 * Always a suggestion: the owner confirms it before it counts as data.
 */
final readonly class ExpenseClassification
{
    public function __construct(
        public string $description,
        public string $lineName,
        public CostCategory $category,
        public ?string $driverCode,
        public float $confidence,
        public string $method,
    ) {}

    /**
     * @return array{description: string, line_name: string, category: string, driver_code: string|null, confidence: float, method: string}
     */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'line_name' => $this->lineName,
            'category' => $this->category->value,
            'driver_code' => $this->driverCode,
            'confidence' => $this->confidence,
            'method' => $this->method,
        ];
    }
}
