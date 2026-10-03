<?php

namespace Tests\Feature\Services\Margin\Ask;

use App\Models\PriceDriver;
use App\Services\Margin\Ask\AskPriceImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AskPriceImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'margin.ask.base_url' => 'https://ask.test/api',
            'margin.ask.subgroups.series' => ['cpi.oils_fats' => ['item' => '7', 'name' => 'HICP 01.1.5 Oils and fats', 'kind' => 'cpi']],
        ]);

        Http::fake([
            'ask.test/api/Prices/Consumer%20Price%20Index/Monthly%20indicators/cpi01.px' => Http::response($this->table(
                ['Viti/muaji' => ['2025M08', '2026M07', '2026M08'], 'variabla' => ['0', '1', '2']],
                [130.0, 0.5, 4.5, null, null, 6.5, null, null, 7.3],
            )),
            'ask.test/api/Prices/Consumer%20Price%20Index/Monthly%20indicators/cpi09.px' => Http::response($this->table(
                ['Viti/muaji' => ['2026M02', '2026M03'], 'Grupet dhe nëngrupet' => ['7']],
                [140.0, 139.44],
            )),
            'ask.test/api/Prices/Consumer%20Price%20Index/Monthly%20indicators/cpi05.px' => Http::response($this->table(
                ['Viti/muaji' => ['2026M02', '2026M03'], 'Grupet dhe nëngrupet' => ['7']],
                [0.1, -0.4],
            )),
            'ask.test/api/Labour%20market/Niveli%20i%20Pagave/tab01.px' => Http::response($this->table(
                ['Viti' => ['0', '1'], 'Variabla' => ['0'], 'Bruto/neto' => ['0']],
                [700.0, 650.0],
                ['Viti' => ['0' => '2025', '1' => '2024']],
            )),
        ]);
    }

    public function test_fetches_the_headline_subgroups_and_annual_wages_as_monthly_series(): void
    {
        $payload = app(AskPriceImporter::class)->fetch();

        $this->assertSame(['cpi.headline', 'cpi.oils_fats', 'wages.kosovo'], array_keys($payload['drivers']));
        $this->assertSame(['2025-08' => 100.0, '2026-08' => 107.3], $payload['drivers']['cpi.headline']['observations']);
        $this->assertSame(['2026-02' => 100.0, '2026-03' => 99.6], $payload['drivers']['cpi.oils_fats']['observations']);
        $this->assertSame(650.0, $payload['drivers']['wages.kosovo']['observations']['2024-12']);
        $this->assertSame(700.0, $payload['drivers']['wages.kosovo']['observations']['2025-01']);

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'cpi05.px')
            && $request['query'][1]['code'] === 'Grupet dhe nëngrupet'
            && $request['query'][1]['selection']['values'] === ['7']
            && $request['response']['format'] === 'json-stat2');
    }

    public function test_store_replaces_the_observations_of_each_driver(): void
    {
        $importer = app(AskPriceImporter::class);
        $importer->store($importer->fetch());
        $importer->store($importer->fetch());

        $headline = PriceDriver::query()->where('code', 'cpi.headline')->sole();
        $this->assertSame('Kosovo Agency of Statistics (ASKdata)', $headline->source);
        $this->assertSame(2, $headline->observations()->count());
    }

    public function test_command_writes_the_offline_snapshot(): void
    {
        $path = storage_path('framework/testing/ask-snapshot.json');
        config(['margin.ask.snapshot_path' => $path]);

        $this->artisan('margin:import-ask', ['--snapshot' => true])->assertSuccessful();

        $snapshot = json_decode((string) file_get_contents($path), true);
        unlink($path);
        $this->assertSame(107.3, $snapshot['drivers']['cpi.headline']['observations']['2026-08']);
    }

    /**
     * A JSON-stat 2.0 dataset as the ASKdata API returns it.
     *
     * @param  array<string, list<string>>  $dimensions  Category ids per dimension, in order.
     * @param  list<float|null>  $values
     * @param  array<string, array<string, string>>  $labels
     * @return array<string, mixed>
     */
    private function table(array $dimensions, array $values, array $labels = []): array
    {
        $dimension = [];

        foreach ($dimensions as $code => $categories) {
            $dimension[$code] = ['category' => [
                'index' => array_flip($categories),
                'label' => $labels[$code] ?? array_combine($categories, $categories),
            ]];
        }

        return [
            'version' => '2.0',
            'class' => 'dataset',
            'id' => array_keys($dimensions),
            'size' => array_map('count', array_values($dimensions)),
            'dimension' => $dimension,
            'value' => $values,
        ];
    }
}
