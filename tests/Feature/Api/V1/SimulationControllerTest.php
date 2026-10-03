<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\SimulationRun;
use App\Models\SupplierOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsDemoCafe;
use Tests\TestCase;

class SimulationControllerTest extends TestCase
{
    use RefreshDatabase, SeedsDemoCafe;

    public function test_fuel_shock_lowers_profit_against_the_same_seed_baseline(): void
    {
        $company = $this->seedDemoCafe();

        $response = $this->postJson(route('api.v1.companies.simulations.store', $company), [
            'shocks' => ['fuel.diesel' => 20],
            'paths' => 500,
        ]);

        $response->assertCreated()->assertJsonPath('data.scenario', 'baseline+what_if');
        $this->assertLessThan(0, $response->json('data.result.presentation.profit_change_vs_baseline.value'));
    }

    public function test_replay_2022_is_labelled_as_an_assumption(): void
    {
        $company = $this->seedDemoCafe();

        $this->postJson(route('api.v1.companies.simulations.store', $company), ['scenario' => 'replay_2022', 'paths' => 500])
            ->assertCreated()
            ->assertJsonPath('data.scenario', 'replay_2022')
            ->assertJsonPath('data.result.presentation.scenario_label', 'assumption');
    }

    public function test_tested_actions_are_reproducible_with_the_same_seed(): void
    {
        $company = $this->seedDemoCafe();
        $offer = $company->supplierOffers()->where('kind', 'alternative_supplier')->firstOrFail();
        $payload = [
            'actions' => [
                ['type' => 'raise_prices', 'steps' => [['month' => 1, 'percent' => 2.5], ['month' => 3, 'percent' => 2.5]]],
                ['type' => 'switch_supplier', 'offer_id' => $offer->id],
            ],
            'paths' => 500,
            'seed' => 42,
        ];

        $first = $this->postJson(route('api.v1.companies.simulations.store', $company), $payload)->assertCreated();
        $second = $this->postJson(route('api.v1.companies.simulations.store', $company), $payload)->assertCreated();

        $this->assertSame('baseline+actions', $first->json('data.scenario'));
        $this->assertSame($first->json('data.result'), $second->json('data.result'));
        $this->assertGreaterThan(0, $first->json('data.result.presentation.profit_change_vs_baseline.value'));
    }

    public function test_rejects_a_shock_on_an_unknown_driver_with_422(): void
    {
        $company = Company::factory()->create();

        $this->postJson(route('api.v1.companies.simulations.store', $company), ['shocks' => ['oil.unknown' => 10]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shocks');
    }

    public function test_rejects_an_offer_from_another_company_with_422(): void
    {
        $company = $this->seedDemoCafe();
        $foreignOffer = SupplierOffer::factory()->create();

        $this->postJson(route('api.v1.companies.simulations.store', $company), [
            'actions' => [['type' => 'switch_supplier', 'offer_id' => $foreignOffer->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors('actions.0.offer_id');
    }

    public function test_returns_404_for_a_run_of_another_company(): void
    {
        $run = SimulationRun::factory()->create();
        $otherCompany = Company::factory()->create();

        $this->getJson(route('api.v1.companies.simulations.show', [$otherCompany, $run]))->assertNotFound();
    }
}
