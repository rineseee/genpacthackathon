<?php

namespace Database\Seeders;

use App\Models\PriceDriver;
use App\Services\PriceDriverCatalog;
use Illuminate\Database\Seeder;

class PriceDriverSeeder extends Seeder
{
    /**
     * Seed source metadata without making network requests or manufacturing observations.
     */
    public function run(): void
    {
        foreach (PriceDriverCatalog::all() as $code => $definition) {
            $driver = PriceDriver::query()->where('code', $code)->first();

            if ($driver !== null && preg_match('/demo|illustrative/i', $driver->source) === 1) {
                $driver->observations()->delete();
                $driver->projections()->where('source', 'like', 'Demo%')->delete();
            }

            PriceDriver::updateOrCreate(['code' => $code], $definition);
        }
    }
}
