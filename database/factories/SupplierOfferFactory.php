<?php

namespace Database\Factories;

use App\Enums\OfferKind;
use App\Models\Company;
use App\Models\CostLine;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierOffer>
 */
class SupplierOfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'supplier_id' => Supplier::factory(),
            'cost_line_id' => CostLine::factory(),
            'kind' => OfferKind::AlternativeSupplier,
            'unit_price' => 1,
            'max_share' => 0.5,
            'duration_months' => null,
            'quoted_on' => now()->startOfMonth(),
        ];
    }
}
