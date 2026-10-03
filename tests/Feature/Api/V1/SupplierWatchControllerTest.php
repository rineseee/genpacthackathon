<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsDemoCafe;
use Tests\TestCase;

class SupplierWatchControllerTest extends TestCase
{
    use RefreshDatabase, SeedsDemoCafe;

    public function test_flags_the_supplier_that_outpaced_its_market_with_drafts_in_both_languages(): void
    {
        $company = $this->seedDemoCafe();

        $response = $this->getJson(route('api.v1.companies.supplier-watch.index', $company));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.supplier.name', 'Qumështorja Prishtina')
            ->assertJsonPath('data.0.cost_line.name', 'Milk')
            ->assertJsonPath('data.0.renegotiation_draft.label', 'ai_suggestion');

        $flag = $response->json('data.0');
        $this->assertGreaterThan($flag['market_change']['value'] + 5, $flag['supplier_price_change']['value']);
        $this->assertStringContainsString($flag['supplier_price_change']['value'].'%', $flag['renegotiation_draft']['messages']['en']);
        $this->assertStringContainsString('Përshëndetje Qumështorja Prishtina', $flag['renegotiation_draft']['messages']['sq']);
    }
}
