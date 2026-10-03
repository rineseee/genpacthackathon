<?php

namespace Tests\Feature\Api\V1;

use Database\Seeders\PriceDriverSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceDriverControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_official_ask_headline_inflation_from_the_snapshot(): void
    {
        $this->seed(PriceDriverSeeder::class);

        $response = $this->getJson(route('api.v1.price-drivers.index'));

        $response->assertOk();
        $headline = collect($response->json('data'))->firstWhere('code', 'cpi.headline');
        $this->assertSame('Kosovo Agency of Statistics (ASKdata)', $headline['source']);
        $this->assertSame('2026-08', $headline['latest']['period']);
        $this->assertSame(7.3, $headline['latest']['change_last_12_months']['value']);
        $this->assertNull($headline['projection']);
    }
}
