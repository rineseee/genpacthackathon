<?php

namespace App\Services\Margin\Ask;

use App\Enums\DriverKind;
use App\Models\PriceDriver;
use Illuminate\Support\Facades\DB;

/**
 * Fetches the price drivers the engine uses from the Kosovo Agency of Statistics and stores them.
 *
 * fetch() returns a plain payload (also written as the offline snapshot); store() saves any payload.
 */
final class AskPriceImporter
{
    public function __construct(
        private AskDataClient $client,
        private IndexChainer $chainer,
    ) {}

    /**
     * @return array{source: string, fetched_at: string, drivers: array<string, array{name: string, kind: string, unit: string, observations: array<string, float>}>}
     */
    public function fetch(): array
    {
        return [
            'source' => (string) config('margin.ask.source'),
            'fetched_at' => now()->toIso8601String(),
            'drivers' => [
                ...$this->headline(),
                ...$this->subgroups(),
                ...$this->wages(),
            ],
        ];
    }

    /**
     * Replace the observations of every driver in the payload. Returns the number of observations stored.
     *
     * @param  array{source: string, drivers: array<string, array{name: string, kind: string, unit: string, observations: array<string, float>}>}  $payload
     */
    public function store(array $payload): int
    {
        return DB::transaction(function () use ($payload): int {
            $stored = 0;

            foreach ($payload['drivers'] as $code => $definition) {
                $driver = PriceDriver::updateOrCreate(['code' => $code], [
                    'name' => $definition['name'],
                    'kind' => DriverKind::from($definition['kind']),
                    'source' => $payload['source'],
                    'unit' => $definition['unit'],
                ]);

                $driver->observations()->delete();
                $rows = [];

                foreach ($definition['observations'] as $period => $value) {
                    $rows[] = [
                        'price_driver_id' => $driver->id,
                        'period' => $period.'-01',
                        'value' => $value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    $driver->observations()->insert($chunk);
                }

                $stored += count($rows);
            }

            return $stored;
        });
    }

    /**
     * @return array<string, array{name: string, kind: string, unit: string, observations: array<string, float>}>
     */
    private function headline(): array
    {
        $config = config('margin.ask.headline');
        $table = $this->client->query($config['table'], [
            $config['time_dimension'] => ['filter' => 'top', 'values' => [(string) config('margin.ask.months')]],
        ]);

        $series = ['level' => [], 'monthly_change' => [], 'annual_change' => []];

        foreach ($table->categories($config['time_dimension']) as $month) {
            foreach (array_keys($series) as $variable) {
                $series[$variable][$this->period($month)] = $table->value([
                    $config['time_dimension'] => $month,
                    $config['variable_dimension'] => $config[$variable],
                ]);
            }
        }

        return [$config['code'] => [
            'name' => $config['name'],
            'kind' => DriverKind::Cpi->value,
            'unit' => 'index (chained, first month = 100)',
            'observations' => $this->chainer->chain($series['level'], $series['monthly_change'], $series['annual_change']),
        ]];
    }

    /**
     * @return array<string, array{name: string, kind: string, unit: string, observations: array<string, float>}>
     */
    private function subgroups(): array
    {
        $config = config('margin.ask.subgroups');
        $items = array_column($config['series'], 'item');
        $selections = [
            $config['time_dimension'] => ['filter' => 'top', 'values' => [(string) config('margin.ask.months')]],
            $config['group_dimension'] => ['filter' => 'item', 'values' => $items],
        ];
        $levels = $this->client->query($config['levels_table'], $selections);
        $changes = $this->client->query($config['changes_table'], $selections);
        $drivers = [];

        foreach ($config['series'] as $code => $definition) {
            $levelSeries = [];
            $changeSeries = [];

            foreach ($levels->categories($config['time_dimension']) as $month) {
                $levelSeries[$this->period($month)] = $levels->value([$config['time_dimension'] => $month, $config['group_dimension'] => $definition['item']]);
            }

            foreach ($changes->categories($config['time_dimension']) as $month) {
                $changeSeries[$this->period($month)] = $changes->value([$config['time_dimension'] => $month, $config['group_dimension'] => $definition['item']]);
            }

            $drivers[$code] = [
                'name' => $definition['name'],
                'kind' => $definition['kind'],
                'unit' => 'index (chained, first month = 100)',
                'observations' => $this->chainer->chain($levelSeries, $changeSeries),
            ];
        }

        return $drivers;
    }

    /**
     * Annual average salary, held flat across the months of each year.
     *
     * @return array<string, array{name: string, kind: string, unit: string, observations: array<string, float>}>
     */
    private function wages(): array
    {
        $config = config('margin.ask.wages');
        $table = $this->client->query($config['table'], [
            $config['year_dimension'] => ['filter' => 'all', 'values' => ['*']],
            $config['variable_dimension'] => ['filter' => 'item', 'values' => [$config['variable']]],
            $config['gross_dimension'] => ['filter' => 'item', 'values' => [$config['gross']]],
        ]);
        $observations = [];

        foreach ($table->categories($config['year_dimension']) as $category) {
            $year = (int) $table->label($config['year_dimension'], $category);
            $salary = $table->value([
                $config['year_dimension'] => $category,
                $config['variable_dimension'] => $config['variable'],
                $config['gross_dimension'] => $config['gross'],
            ]);

            if ($salary === null || $year < 2000) {
                continue;
            }

            for ($month = 1; $month <= 12; $month++) {
                $observations[sprintf('%d-%02d', $year, $month)] = $salary;
            }
        }

        ksort($observations);

        return [$config['code'] => [
            'name' => $config['name'],
            'kind' => DriverKind::Wages->value,
            'unit' => 'EUR per month (annual average)',
            'observations' => array_slice($observations, -(int) config('margin.ask.months'), preserve_keys: true),
        ]];
    }

    /**
     * "2026M08" becomes "2026-08".
     */
    private function period(string $askMonth): string
    {
        return str_replace('M', '-', $askMonth);
    }
}
