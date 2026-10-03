<?php

namespace App\Console\Commands;

use App\Models\DriverProjection;
use App\Models\PriceDriver;
use App\Models\PriceObservation;
use App\Services\PriceDriverCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

#[Signature('margin:import-drivers')]
#[Description('Import source-backed price driver observations')]
class ImportDrivers extends Command
{
    private const string PXWEB_URL = 'https://askdata.rks-gov.net/api/v1/en/ASKdata/Prices/Consumer%20Price%20Index/Monthly%20indicators/cpi08.px';

    /** @var array<string, string> */
    private const array HICP_GROUPS = [
        '0' => 'cpi.headline',
        '1' => 'cpi.food',
        '4' => 'cpi.housing_energy',
        '7' => 'cpi.transport',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $observations = [
                ...$this->fetchHicpObservations(),
                ...$this->readSourceCsv(database_path('data/world_bank_pink_sheet.csv')),
                ...$this->readSourceCsv(database_path('data/eu_milk_prices.csv')),
            ];

            $this->assertMinimumHistory($observations);

            DB::transaction(function () use ($observations): void {
                $drivers = [];

                foreach (PriceDriverCatalog::all() as $code => $definition) {
                    $existingDriver = PriceDriver::query()->where('code', $code)->first();

                    if ($existingDriver !== null && preg_match('/demo|illustrative/i', $existingDriver->source) === 1) {
                        $existingDriver->observations()->delete();
                        $existingDriver->projections()->where('source', 'like', 'Demo%')->delete();
                    }

                    $drivers[$code] = PriceDriver::updateOrCreate(['code' => $code], $definition);
                }

                DriverProjection::query()
                    ->where('source', 'like', 'Demo projection%')
                    ->delete();

                $timestamp = now();
                $records = array_map(fn (array $observation): array => [
                    'price_driver_id' => $drivers[$observation['code']]->id,
                    'period' => $observation['period'],
                    'value' => $observation['value'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ], $observations);

                PriceObservation::query()->upsert(
                    $records,
                    ['price_driver_id', 'period'],
                    ['value', 'updated_at'],
                );
            });
        } catch (Throwable $exception) {
            $this->error('Price driver import failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(count($observations).' source observation(s) imported.');
        $this->line('No verifiable 6-month low/mid/high institutional projections were found; no projections were imported.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{code: string, period: string, value: float}>
     */
    private function fetchHicpObservations(): array
    {
        $metadata = Http::connectTimeout(5)
            ->timeout(30)
            ->get(self::PXWEB_URL)
            ->throw()
            ->json();

        $timeVariable = null;
        $groupVariable = null;

        foreach ($metadata['variables'] ?? [] as $variable) {
            if (($variable['time'] ?? false) === true) {
                $timeVariable = $variable;
            } else {
                $groupVariable = $variable;
            }
        }

        if ($timeVariable === null || $groupVariable === null) {
            throw new RuntimeException('ASKdata HICP table did not provide its expected time and group dimensions.');
        }

        $timeValues = array_slice($timeVariable['values'] ?? [], 0, 60);

        if (count($timeValues) < 24) {
            throw new RuntimeException('ASKdata HICP table provides fewer than 24 monthly periods.');
        }

        $groupValues = array_map('strval', array_keys(self::HICP_GROUPS));
        $selection = [
            [
                'code' => $timeVariable['code'],
                'selection' => ['filter' => 'item', 'values' => $timeValues],
            ],
            [
                'code' => $groupVariable['code'],
                'selection' => ['filter' => 'item', 'values' => $groupValues],
            ],
        ];

        $dataset = Http::connectTimeout(5)
            ->timeout(30)
            ->post(self::PXWEB_URL, [
                'query' => $selection,
                'response' => ['format' => 'json-stat2'],
            ])
            ->throw()
            ->json();

        $timeIndexes = $dataset['dimension'][$timeVariable['code']]['category']['index'] ?? [];
        $groupIndexes = $dataset['dimension'][$groupVariable['code']]['category']['index'] ?? [];
        $values = $dataset['value'] ?? [];

        if (! is_array($timeIndexes) || ! is_array($groupIndexes) || ! is_array($values) || $groupIndexes === []) {
            throw new RuntimeException('ASKdata HICP response was not a valid JSON-stat2 dataset.');
        }

        $observations = [];

        foreach ($timeIndexes as $period => $timeIndex) {
            if (preg_match('/^(\d{4})M(\d{2})$/', (string) $period, $matches) !== 1) {
                continue;
            }

            foreach (self::HICP_GROUPS as $groupCode => $driverCode) {
                if (! array_key_exists($groupCode, $groupIndexes)) {
                    continue;
                }

                $valueIndex = ($timeIndex * count($groupIndexes)) + $groupIndexes[$groupCode];
                $value = $values[$valueIndex] ?? null;

                if (! is_numeric($value) || (float) $value <= 0) {
                    continue;
                }

                $observations[] = [
                    'code' => $driverCode,
                    'period' => $matches[1].'-'.$matches[2].'-01',
                    'value' => (float) $value,
                ];
            }
        }

        return $observations;
    }

    /**
     * @return list<array{code: string, period: string, value: float}>
     */
    private function readSourceCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Could not open source data file: {$path}");
        }

        try {
            $headers = fgetcsv($handle);
            $requiredHeaders = ['driver_code', 'period', 'value', 'source', 'url', 'unit'];

            if (! is_array($headers) || array_diff($requiredHeaders, $headers) !== []) {
                throw new RuntimeException("Source data file is missing required columns: {$path}");
            }

            $positions = array_flip($headers);
            $observations = [];
            $seenPeriods = [];

            while (($row = fgetcsv($handle)) !== false) {
                $code = trim($row[$positions['driver_code']] ?? '');
                $period = trim($row[$positions['period']] ?? '');
                $rawValue = trim($row[$positions['value']] ?? '');
                $source = trim($row[$positions['source']] ?? '');
                $url = trim($row[$positions['url']] ?? '');

                if (! array_key_exists($code, PriceDriverCatalog::all())) {
                    throw new RuntimeException("Source data file contains an unknown driver code: {$code}");
                }

                if (preg_match('/^\d{4}-\d{2}-01$/', $period) !== 1 || ! is_numeric($rawValue) || (float) $rawValue <= 0) {
                    throw new RuntimeException("Source data file contains an invalid period or level for {$code}.");
                }

                if ($source === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
                    throw new RuntimeException("Source data file is missing provenance for {$code} {$period}.");
                }

                $periodKey = $code.'|'.$period;

                if (isset($seenPeriods[$periodKey])) {
                    throw new RuntimeException("Source data file contains a duplicate period for {$code} {$period}.");
                }

                $seenPeriods[$periodKey] = true;
                $observations[] = [
                    'code' => $code,
                    'period' => $period,
                    'value' => (float) $rawValue,
                ];
            }

            return $observations;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<array{code: string, period: string, value: float}>  $observations
     */
    private function assertMinimumHistory(array $observations): void
    {
        $periodsByDriver = [];

        foreach ($observations as $observation) {
            $periodsByDriver[$observation['code']][$observation['period']] = true;
        }

        foreach (['cpi.headline', 'cpi.food', 'cpi.transport', 'cpi.housing_energy', 'commodity.wheat', 'commodity.sunflower_oil', 'commodity.sugar', 'commodity.dairy'] as $code) {
            if (count($periodsByDriver[$code] ?? []) < 24) {
                throw new RuntimeException("Fewer than 24 verified monthly observations were received for {$code}.");
            }
        }
    }
}
