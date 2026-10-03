<?php

namespace Tests;

use App\Models\Company;
use Database\Seeders\DemoBakerySeeder;
use Database\Seeders\PriceDriverSeeder;

/**
 * Seeds the price drivers and the demo bakery, with a small simulation for fast tests.
 */
trait SeedsDemoBakery
{
    protected function seedDemoBakery(): Company
    {
        config(['margin.simulation.paths' => 400]);

        $this->seed([PriceDriverSeeder::class, DemoBakerySeeder::class]);

        return Company::query()->where('name', 'Furra Demo')->firstOrFail();
    }
}
