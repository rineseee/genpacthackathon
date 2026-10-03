<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\MonthlyFinancial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthlyFinancial>
 */
class MonthlyFinancialFactory extends Factory
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
            'period' => now()->startOfMonth(),
            'revenue' => 20000,
            'selling_price_index' => 100,
        ];
    }
}
