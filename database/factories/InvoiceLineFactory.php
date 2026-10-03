<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLine>
 */
class InvoiceLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(10, 500);
        $unitPrice = fake()->randomFloat(2, 0.5, 20);

        return [
            'company_id' => Company::factory(),
            'supplier_id' => null,
            'cost_line_id' => null,
            'invoiced_on' => now()->startOfMonth(),
            'description' => fake()->words(3, true),
            'quantity' => $quantity,
            'unit' => 'kg',
            'unit_price' => $unitPrice,
            'total' => round($quantity * $unitPrice, 2),
        ];
    }
}
