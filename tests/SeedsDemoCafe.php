<?php

namespace Tests;

use App\Models\Company;
use Database\Seeders\DemoCafeSeeder;
use Database\Seeders\PriceDriverSeeder;

/**
 * Seeds the real ASK price drivers (from the committed snapshot) and the demo cafe, with a small simulation for fast tests.
 */
trait SeedsDemoCafe
{
    protected function seedDemoCafe(): Company
    {
        config(['margin.simulation.paths' => 400]);

        $this->seed([PriceDriverSeeder::class, DemoCafeSeeder::class]);

        return Company::query()->where('name', 'Cafe Demo')->firstOrFail();
    }
}
