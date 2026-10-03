<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseClassificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_previews_classifications_and_marks_unknown_lines_for_validation(): void
    {
        $company = Company::factory()->create();

        $this->postJson(route('api.v1.companies.expense-classifications.store', $company), [
            'descriptions' => ['Vaj Luledielli 5L Bimi', 'Shërbim kontabiliteti'],
        ])
            ->assertOk()
            ->assertJsonPath('data.0.classification.driver_code', 'commodity.sunflower_oil')
            ->assertJsonPath('data.0.label', 'ai_suggestion')
            ->assertJsonPath('data.1.classification', null)
            ->assertJsonPath('data.1.label', 'needs_validation');

        $this->assertSame(0, $company->costLines()->count());
    }

    public function test_rejects_an_empty_request_with_422(): void
    {
        $company = Company::factory()->create();

        $this->postJson(route('api.v1.companies.expense-classifications.store', $company), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('descriptions');
    }
}
