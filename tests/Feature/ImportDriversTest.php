<?php

namespace Tests\Feature;

use App\Models\PriceDriver;
use Database\Seeders\PriceDriverSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportDriversTest extends TestCase
{
    use RefreshDatabase;

    private const string PXWEB_URL = 'https://askdata.rks-gov.net/api/v1/en/ASKdata/Prices/Consumer%20Price%20Index/Monthly%20indicators/cpi08.px';

    public function test_seeder_removes_legacy_demo_rows_and_keeps_only_source_metadata(): void
    {
        $legacyDriver = PriceDriver::factory()->create([
            'code' => 'cpi.headline',
            'source' => 'Demo series (illustrative)',
        ]);
        $legacyDriver->observations()->create(['period' => '2025-01-01', 'value' => 123.0]);
        $legacyDriver->projections()->create([
            'horizon_months' => 6,
            'change_low' => 0.01,
            'change_mid' => 0.02,
            'change_high' => 0.03,
            'source' => 'Demo projection (illustrative)',
            'published_on' => '2025-01-01',
        ]);

        $this->seed(PriceDriverSeeder::class);

        $this->assertDatabaseCount('price_drivers', 11);
        $this->assertDatabaseCount('price_observations', 0);
        $this->assertDatabaseCount('driver_projections', 0);
        $this->assertSame(11, PriceDriver::query()->where('source', 'not like', '%demo%')->count());
    }

    public function test_imports_real_series_idempotently_and_removes_synthetic_rows(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::PXWEB_URL => function (Request $request) {
                return $request->method() === 'GET'
                    ? Http::response($this->metadata(), 200)
                    : Http::response($this->dataset(), 200);
            },
        ]);

        $legacyDriver = PriceDriver::factory()->create([
            'code' => 'cpi.headline',
            'source' => 'Demo series (illustrative)',
        ]);
        $legacyDriver->observations()->create(['period' => '2025-01-01', 'value' => 123.0]);
        $legacyDriver->projections()->create([
            'horizon_months' => 6,
            'change_low' => 0.01,
            'change_mid' => 0.02,
            'change_high' => 0.03,
            'source' => 'Demo projection (illustrative)',
            'published_on' => '2025-01-01',
        ]);

        $exitCode = Artisan::call('margin:import-drivers');
        $this->assertSame(0, $exitCode, Artisan::output());

        $exitCode = Artisan::call('margin:import-drivers');
        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertDatabaseCount('price_drivers', 11);
        $this->assertDatabaseCount('price_observations', 288);
        $this->assertDatabaseCount('driver_projections', 0);
        $this->assertSame([
            'commodity.dairy',
            'commodity.sugar',
            'commodity.sunflower_oil',
            'commodity.wheat',
            'cpi.food',
            'cpi.headline',
            'cpi.housing_energy',
            'cpi.transport',
            'energy.electricity',
            'fuel.diesel',
            'wages.kosovo',
        ], PriceDriver::query()->orderBy('code')->pluck('code')->all());

        foreach (['cpi.headline', 'cpi.food', 'cpi.transport', 'cpi.housing_energy', 'commodity.wheat', 'commodity.sunflower_oil', 'commodity.sugar', 'commodity.dairy'] as $code) {
            $driver = PriceDriver::query()->where('code', $code)->firstOrFail();

            $this->assertSame(36, $driver->observations()->count());
            $this->assertNotEmpty($driver->source);
        }

        $this->assertSame(0, PriceDriver::query()->whereIn('code', ['energy.electricity', 'fuel.diesel', 'wages.kosovo'])->withCount('observations')->get()->sum('observations_count'));

        foreach (['cpi.headline' => 156.0, 'cpi.food' => 157.0, 'cpi.housing_energy' => 158.0, 'cpi.transport' => 159.0] as $code => $expectedValue) {
            $driver = PriceDriver::query()->where('code', $code)->firstOrFail();
            $latestValue = $driver->observations()->where('period', '2026-03-01')->value('value');

            $this->assertSame($expectedValue, (float) $latestValue);
        }

        Http::assertSentCount(4);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === self::PXWEB_URL);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === self::PXWEB_URL
            && $request->data()['response']['format'] === 'json-stat2'
            && $request->data()['query'][1]['selection']['values'] === ['0', '1', '4', '7']);
    }

    public function test_failed_askdata_request_does_not_write_partial_imports(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::PXWEB_URL => Http::response(['error' => 'temporarily unavailable'], 503),
        ]);

        $this->artisan('margin:import-drivers')->assertFailed();

        $this->assertDatabaseCount('price_drivers', 0);
        $this->assertDatabaseCount('price_observations', 0);
        $this->assertDatabaseCount('driver_projections', 0);
        Http::assertSentCount(1);
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(): array
    {
        $months = $this->months();

        return [
            'variables' => [
                ['code' => 'Viti/muaji', 'text' => 'Year/month', 'values' => $months, 'time' => true],
                ['code' => 'Grupet kryesore', 'text' => 'Main groups', 'values' => ['0', '1', '4', '7']],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dataset(): array
    {
        $months = $this->months();
        $groups = ['0', '1', '4', '7'];
        $values = [];

        foreach (array_keys($months) as $timeIndex) {
            foreach ($groups as $groupIndex => $group) {
                $values[] = 120 + count($months) - $timeIndex + $groupIndex;
            }
        }

        return [
            'class' => 'dataset',
            'id' => ['Viti/muaji', 'Grupet kryesore'],
            'size' => [count($months), count($groups)],
            'dimension' => [
                'Viti/muaji' => ['category' => ['index' => array_flip($months)]],
                'Grupet kryesore' => ['category' => ['index' => array_flip($groups)]],
            ],
            'value' => $values,
        ];
    }

    /**
     * @return list<string>
     */
    private function months(): array
    {
        $months = [];
        $latestMonth = new \DateTimeImmutable('2026-03-01');

        for ($offset = 0; $offset < 36; $offset++) {
            $month = $latestMonth->modify("-{$offset} months");
            $months[] = $month->format('Y').'M'.$month->format('m');
        }

        return $months;
    }
}
