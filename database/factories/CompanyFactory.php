<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'industry' => 'bakery',
            'locations' => 1,
            'currency' => 'EUR',
            'cash_balance' => 20000,
            'minimum_cash_reserve' => 5000,
            'monthly_non_operating_outflows' => 1000,
            'price_elasticity' => -0.5,
        ];
    }
}
