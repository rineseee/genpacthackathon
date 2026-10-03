<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsDemoCafe;
use Tests\TestCase;

class SupplierWatchControllerTest extends TestCase
{
    use RefreshDatabase, SeedsDemoCafe;

    public function test_flags_the_supplier_against_headline_inflation_when_its_subgroup_is_not_yet_published(): void
    {
        $company = $this->seedDemoCafe();

        $response = $this->getJson(route('api.v1.companies.supplier-watch.index', $company));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.supplier.name', 'Qumështorja Prishtina')
            ->assertJsonPath('data.0.cost_line.name', 'Milk')
            ->assertJsonPath('data.0.renegotiation_draft.label', 'ai_suggestion')
            ->assertJsonPath('data.0.benchmark.code', 'cpi.headline')
            ->assertJsonPath('data.0.benchmark.is_fallback', true);

        $flag = $response->json('data.0');
        $this->assertGreaterThan($flag['market_change']['value'] + 5, $flag['supplier_price_change']['value']);
        $this->assertStringContainsString($flag['supplier_price_change']['value'].'%', $flag['renegotiation_draft']['messages']['en']);
        $this->assertStringContainsString('Përshëndetje Qumështorja Prishtina', $flag['renegotiation_draft']['messages']['sq']);
    }
}
