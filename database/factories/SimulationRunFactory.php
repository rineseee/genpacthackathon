<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SimulationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SimulationRun>
 */
class SimulationRunFactory extends Factory
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
            'scenario' => 'baseline',
            'seed' => 2026,
            'paths' => 1000,
            'horizon_months' => 6,
            'input' => [],
            'result' => [],
        ];
    }
}
