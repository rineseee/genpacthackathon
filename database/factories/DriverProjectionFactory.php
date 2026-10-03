<?php

namespace Database\Factories;

use App\Models\DriverProjection;
use App\Models\PriceDriver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverProjection>
 */
class DriverProjectionFactory extends Factory
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
            'horizon_months' => 6,
            'change_low' => 0.0,
            'change_mid' => 0.03,
            'change_high' => 0.06,
            'source' => 'Test projection',
            'published_on' => now()->startOfMonth(),
        ];
    }
}
