<?php

namespace App\Services\Margin;

/**
 * The company's own inflation over the last year, measured from its invoices and sales.
 */
final readonly class CompanyInflation
{
    /**
     * @param  array<int, float>  $lineInflation  Year-over-year unit price change per cost line id.
     */
    public function __construct(
        public ?float $costInflation,
        public ?float $sellingPriceGrowth,
        public ?float $headlineCpi,
        public ?string $headlineCpiPeriod,
        public float $profitLostThisMonth,
        public float $extraCostsThisMonth,
        public float $extraRevenueThisMonth,
        public array $lineInflation,
    ) {}
}
