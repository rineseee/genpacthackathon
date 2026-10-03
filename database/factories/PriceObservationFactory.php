<?php

namespace Database\Factories;

use App\Models\PriceDriver;
use App\Models\PriceObservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceObservation>
 */
class PriceObservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'price_driver_id' => PriceDriver::factory(),
            'period' => now()->startOfMonth(),
            'value' => 100,
        ];
    }
}
