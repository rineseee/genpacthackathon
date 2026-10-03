<?php

namespace Tests\Feature\Api\V1;

use App\Models\PriceDriver;
use Database\Seeders\PriceDriverSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceDriverControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_latest_cpi_change_without_an_unverified_projection(): void
    {
        $this->seed(PriceDriverSeeder::class);
        $transportDriver = PriceDriver::query()->where('code', 'cpi.transport')->firstOrFail();
        $transportDriver->update(['source' => 'Test-only CPI fixture']);
        $transportDriver->observations()->createMany([
            ['period' => '2025-08-01', 'value' => 100],
            ['period' => '2026-08-01', 'value' => 121.1],
        ]);

        $response = $this->getJson(route('api.v1.price-drivers.index'));

        $response->assertOk();
        $transport = collect($response->json('data'))->firstWhere('code', 'cpi.transport');
        $this->assertSame('2026-08', $transport['latest']['period']);
        $this->assertSame(21.1, $transport['latest']['change_last_12_months']['value']);
        $this->assertNull($transport['projection']);
    }
}
