<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AlertMetric;
use App\Models\AlertRule;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_rule_and_returns_201(): void
    {
        $company = Company::factory()->create();

        $this->postJson(route('api.v1.companies.alert-rules.store', $company), [
            'metric' => 'margin_at_risk',
            'threshold' => 2500,
            'recipient_email' => 'owner@example.com',
        ])->assertCreated()->assertJsonPath('data.metric', 'margin_at_risk');

        $this->assertSame(1, $company->alertRules()->count());
    }

    public function test_rejects_a_stress_probability_threshold_above_one_with_422(): void
    {
        $company = Company::factory()->create();

        $this->postJson(route('api.v1.companies.alert-rules.store', $company), [
            'metric' => 'stress_probability',
            'threshold' => 58,
            'recipient_email' => 'owner@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors('threshold');
    }

    public function test_rejects_a_second_rule_for_the_same_metric_with_422(): void
    {
        $rule = AlertRule::factory()->create(['metric' => AlertMetric::StressProbability]);

        $this->postJson(route('api.v1.companies.alert-rules.store', $rule->company_id), [
            'metric' => 'stress_probability',
            'threshold' => 0.5,
            'recipient_email' => 'owner@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors('metric');
    }

    public function test_updates_and_deletes_only_rules_of_the_company(): void
    {
        $rule = AlertRule::factory()->create(['threshold' => 0.3]);
        $otherCompany = Company::factory()->create();

        $this->patchJson(route('api.v1.companies.alert-rules.update', [$rule->company_id, $rule]), ['threshold' => 0.5])
            ->assertOk()
            ->assertJsonPath('data.threshold', 0.5);
        $this->deleteJson(route('api.v1.companies.alert-rules.destroy', [$otherCompany, $rule]))->assertNotFound();
        $this->deleteJson(route('api.v1.companies.alert-rules.destroy', [$rule->company_id, $rule]))->assertNoContent();

        $this->assertModelMissing($rule);
    }
}
