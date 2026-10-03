<?php

namespace Database\Seeders;

use App\Services\Margin\Ask\AskPriceImporter;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Price drivers from the Kosovo Agency of Statistics, loaded from the committed snapshot of real
 * ASKdata series (refresh it with `php artisan margin:import-ask --snapshot`).
 */
class PriceDriverSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(AskPriceImporter $importer): void
    {
        $path = (string) config('margin.ask.snapshot_path');

        if (! is_file($path)) {
            throw new RuntimeException("ASK snapshot not found at {$path}. Run `php artisan margin:import-ask --snapshot` first.");
        }

        $importer->store(json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR));
    }
}
