<?php

namespace Database\Factories;

use App\Enums\AlertMetric;
use App\Models\AlertRule;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertRule>
 */
class AlertRuleFactory extends Factory
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
            'metric' => AlertMetric::StressProbability,
            'threshold' => 0.3,
            'recipient_email' => fake()->safeEmail(),
            'last_triggered_at' => null,
        ];
    }
}
