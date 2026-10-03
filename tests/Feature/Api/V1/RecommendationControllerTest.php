<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsDemoBakery;
use Tests\TestCase;

class RecommendationControllerTest extends TestCase
{
    use RefreshDatabase, SeedsDemoBakery;

    public function test_every_recommended_action_has_a_positive_euro_value_and_the_plan_beats_doing_nothing(): void
    {
        $company = $this->seedDemoBakery();

        $response = $this->getJson(route('api.v1.companies.recommendations.index', $company));

        $response->assertOk();
        $actions = $response->json('data.actions');
        $this->assertNotEmpty($actions);

        foreach ($actions as $action) {
            $this->assertSame('ai_suggestion', $action['label']);
            $this->assertGreaterThan(0, $action['expected_profit_gain']['value']);
        }

        $planTypes = array_column(array_column($response->json('data.plan.actions'), 'action'), 'type');
        $this->assertContains('raise_prices', $planTypes);
        $this->assertContains('fixed_price', $planTypes);
        $this->assertContains('switch_supplier', $planTypes);
        $this->assertGreaterThan(
            $response->json('data.do_nothing.profit_at_horizon.value'),
            $response->json('data.plan.outcome.profit_at_horizon.value'),
        );
    }

    public function test_price_rise_comes_with_its_break_even_volume_loss(): void
    {
        $company = $this->seedDemoBakery();

        $actions = collect($this->getJson(route('api.v1.companies.recommendations.index', $company))->json('data.actions'));
        $pricing = $actions->firstWhere('action.type', 'raise_prices');

        $this->assertNotNull($pricing);
        $this->assertSame('percent', $pricing['metrics']['break_even_volume_loss']['unit']);
        $this->assertSame('assumption', $pricing['metrics']['assumed_price_elasticity']['label']);
    }
}
