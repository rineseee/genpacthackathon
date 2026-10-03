<?php

namespace Database\Factories;

use App\Enums\CostCategory;
use App\Enums\MappingStatus;
use App\Models\Company;
use App\Models\CostLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostLine>
 */
class CostLineFactory extends Factory
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
            'price_driver_id' => null,
            'name' => fake()->unique()->words(2, true),
            'category' => CostCategory::Ingredients,
            'unit' => 'kg',
            'scales_with_volume' => true,
            'storable' => false,
            'storage_cost_rate' => 0,
            'mapping_status' => MappingStatus::Confirmed,
            'mapping_confidence' => 1,
        ];
    }

    public function suggested(): static
    {
        return $this->state(fn (array $attributes) => [
            'mapping_status' => MappingStatus::Suggested,
            'mapping_confidence' => 0.7,
        ]);
    }
}
