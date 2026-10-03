<?php

namespace Tests\Feature\Api\V1;

use App\Models\SimulationRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\SeedsDemoBakery;
use Tests\TestCase;

class CompanyRadarControllerTest extends TestCase
{
    use RefreshDatabase, SeedsDemoBakery;

    public function test_returns_labelled_headline_numbers_for_the_demo_bakery(): void
    {
        $company = $this->seedDemoBakery();

        $response = $this->getJson(route('api.v1.companies.radar.show', $company));

        $response->assertOk()
            ->assertJsonPath('data.as_of', '2026-09')
            ->assertJsonPath('data.profit_today', ['value' => 8000, 'unit' => 'EUR', 'label' => 'data'])
            ->assertJsonPath('data.headline_cpi.value', 7.3)
            ->assertJsonPath('data.margin_at_risk_next_quarter.label', 'forecast')
            ->assertJsonPath('data.supplier_flags.count', 1)
            ->assertJsonPath('data.summary.grounded', true);

        $doNothing = $response->json('data.do_nothing.profit_at_horizon');
        $withPlan = $response->json('data.with_plan.profit_at_horizon');
        $this->assertLessThan($doNothing['value'], $doNothing['low']);
        $this->assertGreaterThan($doNothing['value'], $doNothing['high']);
        $this->assertGreaterThan($doNothing['value'], $withPlan['value']);
        $this->assertLessThan(8000, $doNothing['value']);
    }

    public function test_serves_the_same_payload_from_a_cache_that_does_not_unserialize_objects(): void
    {
        config(['cache.default' => 'file', 'cache.stores.file.path' => storage_path('framework/testing/cache')]);
        $company = $this->seedDemoBakery();

        $fresh = $this->getJson(route('api.v1.companies.radar.show', $company))->assertOk()->json();
        $cached = $this->getJson(route('api.v1.companies.radar.show', $company))->assertOk()->json();

        Cache::flush();
        $this->assertSame($fresh, $cached);
        $this->assertSame(['value' => 8000, 'unit' => 'EUR', 'label' => 'data'], $cached['data']['profit_today']);
    }

    public function test_stores_the_baseline_and_plan_runs_for_audit(): void
    {
        $company = $this->seedDemoBakery();

        $this->getJson(route('api.v1.companies.radar.show', $company))->assertOk();

        $this->assertEqualsCanonicalizing(['baseline', 'plan'], SimulationRun::query()->pluck('scenario')->all());
        $this->assertArrayHasKey('stress_probability', SimulationRun::query()->where('scenario', 'baseline')->sole()->result);
    }

    public function test_returns_404_for_an_unknown_company(): void
    {
        $this->getJson(route('api.v1.companies.radar.show', 999))->assertNotFound();
    }
}
