<?php

namespace Tests\Feature\Api\V1;

use Database\Seeders\PriceDriverSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceDriverControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_latest_change_anchored_to_the_published_cpi_and_a_projection_range(): void
    {
        $this->seed(PriceDriverSeeder::class);

        $response = $this->getJson(route('api.v1.price-drivers.index'));

        $response->assertOk();
        $transport = collect($response->json('data'))->firstWhere('code', 'cpi.transport');
        $this->assertSame('2026-08', $transport['latest']['period']);
        $this->assertSame(21.1, $transport['latest']['change_last_12_months']['value']);
        $this->assertSame('forecast', $transport['projection']['change']['label']);
        $this->assertLessThan($transport['projection']['change']['high'], $transport['projection']['change']['low']);
    }
}
