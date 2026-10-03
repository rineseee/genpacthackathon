<?php

namespace Tests\Feature\Api\V1;

use App\Enums\MappingStatus;
use App\Models\Company;
use App\Models\CostLine;
use App\Models\PriceDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsDemoCafe;
use Tests\TestCase;

class CostLineControllerTest extends TestCase
{
    use RefreshDatabase, SeedsDemoCafe;

    public function test_lists_cost_lines_with_learned_pass_through_and_forecast_ranges(): void
    {
        $company = $this->seedDemoCafe();

        $response = $this->getJson(route('api.v1.companies.cost-lines.index', $company));

        $response->assertOk()->assertJsonCount(11, 'data');
        $milk = collect($response->json('data'))->firstWhere('name', 'Milk');
        $this->assertSame('company_history', $milk['pass_through']['source']);
        $this->assertSame(2, $milk['pass_through']['lag_months']);
        $this->assertLessThan($milk['price_forecast']['high'], $milk['price_forecast']['low']);
        $this->assertContains($milk['price_forecast']['confidence'], ['high', 'medium', 'low']);

        $packaging = collect($response->json('data'))->firstWhere('name', 'Packaging');
        $this->assertSame('needs_validation', $packaging['mapping']['label']);
    }

    public function test_correcting_the_driver_confirms_the_mapping(): void
    {
        $costLine = CostLine::factory()->suggested()->create();
        $driver = PriceDriver::factory()->create();

        $response = $this->patchJson(route('api.v1.companies.cost-lines.update', [$costLine->company_id, $costLine]), [
            'price_driver_id' => $driver->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.mapping.status', 'confirmed')
            ->assertJsonPath('data.driver.code', $driver->code);
        $this->assertSame(MappingStatus::Confirmed, $costLine->fresh()->mapping_status);
    }

    public function test_returns_404_for_a_cost_line_of_another_company(): void
    {
        $costLine = CostLine::factory()->create();
        $otherCompany = Company::factory()->create();

        $this->patchJson(route('api.v1.companies.cost-lines.update', [$otherCompany, $costLine]), ['mapping_status' => 'confirmed'])
            ->assertNotFound();
    }

    public function test_rejects_an_unknown_driver_with_422(): void
    {
        $costLine = CostLine::factory()->create();

        $this->patchJson(route('api.v1.companies.cost-lines.update', [$costLine->company_id, $costLine]), ['price_driver_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('price_driver_id');
    }
}
