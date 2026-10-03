<?php

namespace Tests;

use App\Models\Company;
use App\Models\PriceDriver;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoBakerySeeder;
use Database\Seeders\PriceDriverSeeder;

/**
 * Seeds explicitly test-only driver histories and the demo bakery for deterministic behavior tests.
 */
trait SeedsDemoBakery
{
    /**
     * @var array<string, array{previous: float, latest: float, wiggle: float}>
     */
    private const array TestDriverChanges = [
        'cpi.headline' => ['previous' => 0.04, 'latest' => 0.073, 'wiggle' => 0.002],
        'cpi.food' => ['previous' => 0.06, 'latest' => 0.026, 'wiggle' => 0.003],
        'cpi.transport' => ['previous' => 0.03, 'latest' => 0.211, 'wiggle' => 0.01],
        'cpi.housing_energy' => ['previous' => 0.05, 'latest' => 0.118, 'wiggle' => 0.005],
        'commodity.wheat' => ['previous' => -0.05, 'latest' => 0.06, 'wiggle' => 0.03],
        'commodity.sunflower_oil' => ['previous' => 0.10, 'latest' => 0.14, 'wiggle' => 0.03],
        'commodity.sugar' => ['previous' => 0.05, 'latest' => -0.04, 'wiggle' => 0.025],
        'commodity.dairy' => ['previous' => 0.04, 'latest' => 0.09, 'wiggle' => 0.015],
        'energy.electricity' => ['previous' => 0.06, 'latest' => 0.40, 'wiggle' => 0.06],
        'fuel.diesel' => ['previous' => -0.03, 'latest' => 0.24, 'wiggle' => 0.03],
        'wages.kosovo' => ['previous' => 0.0, 'latest' => 0.10, 'wiggle' => 0.02],
    ];

    protected function seedDemoBakery(): Company
    {
        config(['margin.simulation.paths' => 400]);

        $this->seed(PriceDriverSeeder::class);
        $this->seedTestDriverObservations();
        $this->seed(DemoBakerySeeder::class);

        return Company::query()->where('name', 'Furra Demo')->firstOrFail();
    }

    private function seedTestDriverObservations(): void
    {
        $end = CarbonImmutable::createFromFormat('!Y-m', '2026-09');
        $drivers = PriceDriver::query()->get()->keyBy('code');

        foreach (self::TestDriverChanges as $code => $changes) {
            $driver = $drivers[$code];
            $value = 100.0;

            for ($index = 0; $index < 24; $index++) {
                $period = $end->subMonths(23 - $index);
                $annualChange = $index < 12 ? $changes['previous'] : $changes['latest'];
                $monthOfYear = $index % 12;
                $angle = 2 * M_PI * $monthOfYear / 12;
                $noise = $changes['wiggle'] * (sin($angle) + 0.5 * sin(2 * $angle));
                $value *= exp(log(1 + $annualChange) / 12 + $noise);

                $driver->observations()->create([
                    'period' => $period->toDateString(),
                    'value' => $value,
                ]);
            }

            $driver->update(['source' => 'Test-only deterministic price-driver fixture']);
        }
    }
}
