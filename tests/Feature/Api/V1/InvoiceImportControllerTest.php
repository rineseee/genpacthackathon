<?php

namespace Tests\Feature\Api\V1;

use App\Enums\MappingStatus;
use App\Models\Company;
use App\Models\PriceDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class InvoiceImportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_rows_and_suggests_cost_lines_for_owner_validation(): void
    {
        $company = Company::factory()->create();
        PriceDriver::factory()->create(['code' => 'commodity.sunflower_oil']);
        $csv = implode("\n", [
            'date,supplier,description,quantity,unit,unit_price',
            '2026-09-03,Distributori Dardana,Vaj Luledielli 5L Bimi,100,l,"2,45"',
            '2026-09-10,Distributori Dardana,Vaj Luledielli 5L Bimi,120,l,2.50',
            '2026-09-12,Zyra,Shërbim kontabiliteti,1,month,150',
            'not-a-date,X,Miell,1,kg,0.5',
        ]);

        $response = $this->post(route('api.v1.companies.invoice-imports.store', $company), [
            'file' => UploadedFile::fake()->createWithContent('invoices.csv', $csv),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.imported', 3)
            ->assertJsonPath('data.classified', 2)
            ->assertJsonPath('data.new_cost_lines', ['Cooking oil'])
            ->assertJsonPath('data.needs_validation.0.description', 'Shërbim kontabiliteti')
            ->assertJsonPath('data.errors.0.row', 5);

        $costLine = $company->costLines()->sole();
        $this->assertSame(MappingStatus::Suggested, $costLine->mapping_status);
        $this->assertSame(2.45, $company->invoiceLines()->orderBy('invoiced_on')->first()->unit_price);
    }

    public function test_rejects_a_file_without_the_expected_columns_with_422(): void
    {
        $company = Company::factory()->create();

        $response = $this->post(route('api.v1.companies.invoice-imports.store', $company), [
            'file' => UploadedFile::fake()->createWithContent('invoices.csv', "foo,bar\n1,2"),
        ], ['Accept' => 'application/json']);

        $response->assertUnprocessable()->assertJsonPath('data.imported', 0);
        $this->assertSame(0, $company->invoiceLines()->count());
    }
}
