<?php

namespace Database\Factories;

use App\Enums\DriverKind;
use App\Models\PriceDriver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceDriver>
 */
class PriceDriverFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'commodity.'.fake()->unique()->word(),
            'name' => fake()->words(2, true),
            'kind' => DriverKind::Commodity,
            'source' => 'Test series',
            'unit' => 'index',
        ];
    }

    public function kind(DriverKind $kind): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => $kind,
        ]);
    }
}
