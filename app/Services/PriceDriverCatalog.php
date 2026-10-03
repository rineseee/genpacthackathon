<?php

namespace App\Services;

use App\Enums\DriverKind;

final class PriceDriverCatalog
{
    /**
     * @return array<string, array{name: string, kind: DriverKind, source: string, unit: string}>
     */
    public static function all(): array
    {
        return [
            'cpi.headline' => [
                'name' => 'Kosovo HICP, all items',
                'kind' => DriverKind::Cpi,
                'source' => 'Kosovo Agency of Statistics (ASKdata), HICP cpi08.px, COICOP 00. Total HICP',
                'unit' => 'index (2015=100)',
            ],
            'cpi.food' => [
                'name' => 'Kosovo HICP, food and non-alcoholic beverages',
                'kind' => DriverKind::Cpi,
                'source' => 'Kosovo Agency of Statistics (ASKdata), HICP cpi08.px, COICOP 01. Food and non-alcoholic beverages',
                'unit' => 'index (2015=100)',
            ],
            'cpi.transport' => [
                'name' => 'Kosovo HICP, transport',
                'kind' => DriverKind::Cpi,
                'source' => 'Kosovo Agency of Statistics (ASKdata), HICP cpi08.px, COICOP 07. Transport',
                'unit' => 'index (2015=100)',
            ],
            'cpi.housing_energy' => [
                'name' => 'Kosovo HICP, housing, water, electricity, gas and other fuels',
                'kind' => DriverKind::Cpi,
                'source' => 'Kosovo Agency of Statistics (ASKdata), HICP cpi08.px, COICOP 04. Housing, water, electricity, gas and other fuels',
                'unit' => 'index (2015=100)',
            ],
            'commodity.wheat' => [
                'name' => 'Wheat, US HRW benchmark',
                'kind' => DriverKind::Commodity,
                'source' => 'World Bank Commodity Price Data (Pink Sheet), Wheat, US HRW',
                'unit' => 'USD/metric ton',
            ],
            'commodity.sunflower_oil' => [
                'name' => 'Sunflower oil benchmark',
                'kind' => DriverKind::Commodity,
                'source' => 'World Bank Commodity Price Data (Pink Sheet), Sunflower oil',
                'unit' => 'USD/metric ton',
            ],
            'commodity.sugar' => [
                'name' => 'World sugar benchmark',
                'kind' => DriverKind::Commodity,
                'source' => 'World Bank Commodity Price Data (Pink Sheet), Sugar, world',
                'unit' => 'USD/kg',
            ],
            'commodity.dairy' => [
                'name' => 'EU27 raw milk price benchmark',
                'kind' => DriverKind::Commodity,
                'source' => 'European Commission Milk Market Observatory, EU27 raw milk price (not Kosovo-specific)',
                'unit' => 'EUR/100 kg',
            ],
            'energy.electricity' => [
                'name' => 'Kosovo business electricity tariff',
                'kind' => DriverKind::Energy,
                'source' => 'Kosovo Energy Regulatory Office (ERO), approved electricity tariff decisions; no monthly tariff series imported',
                'unit' => 'EUR/kWh',
            ],
            'fuel.diesel' => [
                'name' => 'Kosovo diesel pump price',
                'kind' => DriverKind::Fuel,
                'source' => 'Kosovo Agency of Statistics (ASKdata), HICP transport prices; no diesel-specific monthly price imported',
                'unit' => 'EUR/liter',
            ],
            'wages.kosovo' => [
                'name' => 'Kosovo average wages',
                'kind' => DriverKind::Wages,
                'source' => 'Kosovo Agency of Statistics (ASKdata), Average salary by economic activity, tab03.px (annual, 2020-2025)',
                'unit' => 'EUR/month (annual series)',
            ],
        ];
    }
}
