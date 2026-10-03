<?php

namespace Database\Seeders;

use App\Enums\DriverKind;
use App\Models\PriceDriver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Public price drivers for Kosovo with 24 months of history and 6-month projections.
 *
 * The CPI series are synthetic, but anchored so their latest year-over-year change equals the
 * Kosovo Agency of Statistics HICP release for August 2026 (headline 7.3%, food 2.6%, transport
 * 21.1%, housing/water/energy/fuel 11.8%). Commodity, energy, fuel and wage series and every
 * projection are illustrative demo data standing in for real feeds.
 */
class PriceDriverSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, kind: DriverKind, source: string, unit: string, ends: string, previous_year: float, last_year: float, wiggle: float, steps?: array<string, float>, projection: array{0: float, 1: float, 2: float}}>
     */
    private const array Drivers = [
        'cpi.headline' => ['name' => 'Kosovo HICP, all items', 'kind' => DriverKind::Cpi, 'source' => 'Kosovo Agency of Statistics HICP (demo series anchored to the Aug 2026 release)', 'unit' => 'index', 'ends' => '2026-08', 'previous_year' => 0.040, 'last_year' => 0.073, 'wiggle' => 0.002, 'projection' => [0.025, 0.050, 0.075]],
        'cpi.food' => ['name' => 'Kosovo HICP, food', 'kind' => DriverKind::Cpi, 'source' => 'Kosovo Agency of Statistics HICP (demo series anchored to the Aug 2026 release)', 'unit' => 'index', 'ends' => '2026-08', 'previous_year' => 0.060, 'last_year' => 0.026, 'wiggle' => 0.003, 'projection' => [0.010, 0.040, 0.070]],
        'cpi.transport' => ['name' => 'Kosovo HICP, transport', 'kind' => DriverKind::Cpi, 'source' => 'Kosovo Agency of Statistics HICP (demo series anchored to the Aug 2026 release)', 'unit' => 'index', 'ends' => '2026-08', 'previous_year' => 0.030, 'last_year' => 0.211, 'wiggle' => 0.010, 'projection' => [0.030, 0.100, 0.180]],
        'cpi.housing_energy' => ['name' => 'Kosovo HICP, housing, water, energy and fuel', 'kind' => DriverKind::Cpi, 'source' => 'Kosovo Agency of Statistics HICP (demo series anchored to the Aug 2026 release)', 'unit' => 'index', 'ends' => '2026-08', 'previous_year' => 0.050, 'last_year' => 0.118, 'wiggle' => 0.005, 'projection' => [0.040, 0.090, 0.140]],
        'commodity.wheat' => ['name' => 'Milling wheat', 'kind' => DriverKind::Commodity, 'source' => 'Demo series (illustrative, stands in for a market price feed)', 'unit' => 'EUR/t index', 'ends' => '2026-09', 'previous_year' => -0.050, 'last_year' => 0.060, 'wiggle' => 0.030, 'projection' => [0.020, 0.220, 0.420]],
        'commodity.sunflower_oil' => ['name' => 'Sunflower oil', 'kind' => DriverKind::Commodity, 'source' => 'Demo series (illustrative, stands in for a market price feed)', 'unit' => 'EUR/t index', 'ends' => '2026-09', 'previous_year' => 0.100, 'last_year' => 0.140, 'wiggle' => 0.030, 'projection' => [0.050, 0.250, 0.450]],
        'commodity.sugar' => ['name' => 'White sugar', 'kind' => DriverKind::Commodity, 'source' => 'Demo series (illustrative, stands in for a market price feed)', 'unit' => 'EUR/t index', 'ends' => '2026-09', 'previous_year' => 0.050, 'last_year' => -0.040, 'wiggle' => 0.025, 'projection' => [-0.050, 0.040, 0.120]],
        'commodity.dairy' => ['name' => 'Dairy (butter and milk)', 'kind' => DriverKind::Commodity, 'source' => 'Demo series (illustrative, stands in for a market price feed)', 'unit' => 'index', 'ends' => '2026-09', 'previous_year' => 0.040, 'last_year' => 0.090, 'wiggle' => 0.015, 'projection' => [0.030, 0.120, 0.220]],
        'energy.electricity' => ['name' => 'Business electricity tariff', 'kind' => DriverKind::Energy, 'source' => 'Demo series (illustrative regulated tariff steps)', 'unit' => 'EUR/kWh index', 'ends' => '2026-09', 'previous_year' => 0.0, 'last_year' => 0.0, 'wiggle' => 0.0, 'steps' => ['2025-03' => 0.06, '2026-02' => 0.12], 'projection' => [0.200, 0.400, 0.600]],
        'fuel.diesel' => ['name' => 'Diesel pump price', 'kind' => DriverKind::Fuel, 'source' => 'Demo series (illustrative, stands in for a fuel price feed)', 'unit' => 'EUR/l index', 'ends' => '2026-09', 'previous_year' => -0.030, 'last_year' => 0.240, 'wiggle' => 0.030, 'projection' => [0.050, 0.250, 0.450]],
        'wages.kosovo' => ['name' => 'Kosovo private-sector wages', 'kind' => DriverKind::Wages, 'source' => 'Demo series (illustrative, minimum wage steps)', 'unit' => 'index', 'ends' => '2026-09', 'previous_year' => 0.0, 'last_year' => 0.0, 'wiggle' => 0.0, 'steps' => ['2025-01' => 0.05, '2026-01' => 0.08], 'projection' => [0.070, 0.100, 0.130]],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        mt_srand(7);

        foreach (self::Drivers as $code => $definition) {
            $driver = PriceDriver::updateOrCreate(['code' => $code], [
                'name' => $definition['name'],
                'kind' => $definition['kind'],
                'source' => $definition['source'],
                'unit' => $definition['unit'],
            ]);

            $driver->observations()->delete();
            $driver->observations()->createMany($this->observations($definition));

            $driver->projections()->delete();
            [$low, $mid, $high] = $definition['projection'];
            $driver->projections()->create([
                'horizon_months' => 6,
                'change_low' => $low,
                'change_mid' => $mid,
                'change_high' => $high,
                'source' => 'Demo projection (illustrative, stands in for central-bank and market forecasts)',
                'published_on' => '2026-09-30',
            ]);
        }
    }

    /**
     * 24 monthly values ending at the driver's latest release. Each year's monthly changes
     * sum exactly to that year's target, so the latest year-over-year change is exact.
     *
     * @param  array<string, mixed>  $definition
     * @return list<array{period: string, value: float}>
     */
    private function observations(array $definition): array
    {
        $end = CarbonImmutable::createFromFormat('!Y-m', $definition['ends']);
        $logChanges = [
            ...$this->yearOfChanges($definition['previous_year'], $definition['wiggle']),
            ...$this->yearOfChanges($definition['last_year'], $definition['wiggle']),
        ];

        $value = 100.0;
        $rows = [];

        foreach ($logChanges as $index => $logChange) {
            $period = $end->subMonths(23 - $index);
            $value *= exp($logChange) * (1 + ($definition['steps'][$period->format('Y-m')] ?? 0.0));
            $rows[] = ['period' => $period->toDateString(), 'value' => round($value, 4)];
        }

        return $rows;
    }

    /**
     * @return list<float>
     */
    private function yearOfChanges(float $annualChange, float $wiggle): array
    {
        $noise = array_map(fn (): float => (mt_rand() / mt_getrandmax() * 2 - 1) * $wiggle, range(1, 12));
        $meanNoise = array_sum($noise) / 12;

        return array_map(fn (float $value): float => log(1 + $annualChange) / 12 + $value - $meanNoise, $noise);
    }
}
