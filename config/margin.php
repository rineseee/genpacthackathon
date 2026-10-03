<?php

use App\Enums\CostCategory;

return [

    /*
    |--------------------------------------------------------------------------
    | Simulation
    |--------------------------------------------------------------------------
    |
    | Monte Carlo settings for the profit and cash-flow model. The seed makes
    | every run reproducible, so any number shown to an owner can be re-derived.
    |
    */

    'simulation' => [
        'paths' => (int) env('MARGIN_SIMULATION_PATHS', 5000),
        'horizon_months' => 6,
        'seed' => 2026,
        'driver_correlation' => 0.5,
        'volume_volatility' => 0.03,
        'baseline_months' => 3,
    ],

    'forecast_horizon_months' => 3,

    /*
    |--------------------------------------------------------------------------
    | Pass-through estimation
    |--------------------------------------------------------------------------
    |
    | How strongly and how quickly a public price driver moves one cost line.
    | Estimated from the company's own invoices when there is enough history,
    | otherwise taken from these industry defaults (labelled as assumptions).
    |
    */

    'pass_through' => [
        'min_observations' => 8,
        'max_lag_months' => 3,
        'min_r_squared' => 0.25,
        'defaults' => [
            'cpi' => ['pass_through' => 1.0, 'lag_months' => 0],
            'commodity' => ['pass_through' => 0.6, 'lag_months' => 2],
            'energy' => ['pass_through' => 0.9, 'lag_months' => 1],
            'fuel' => ['pass_through' => 0.8, 'lag_months' => 0],
            'wages' => ['pass_through' => 1.0, 'lag_months' => 0],
            'fx' => ['pass_through' => 0.5, 'lag_months' => 2],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supplier watch
    |--------------------------------------------------------------------------
    */

    'supplier_watch' => [
        'window_months' => 6,
        'excess_threshold' => 0.05,
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerts
    |--------------------------------------------------------------------------
    */

    'alerts' => [
        'cooldown_days' => 7,
        'n8n_webhook_url' => env('MARGIN_ALERT_WEBHOOK_URL', 'http://localhost:5678/webhook/margin-alert'),
        'n8n_token' => env('MARGIN_ALERT_WEBHOOK_TOKEN'),
        'dashboard_url' => env('APP_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Expense classification rules
    |--------------------------------------------------------------------------
    |
    | Keyword rules (Albanian and English) used to link a messy invoice line to
    | a cost category and price driver. Text is lower-cased and stripped of
    | diacritics before matching. The first matching rule wins, so specific
    | rules come before generic ones. The owner confirms every suggestion.
    |
    */

    'classification_rules' => [
        ['pattern' => '/\b(nafte|dizel|diesel|karburant|benzin|fuel|gasoline)/', 'driver' => 'cpi.transport_fuel', 'category' => CostCategory::Transport, 'line' => 'Fuel', 'confidence' => 0.9],
        ['pattern' => '/\b(miell|mielli|flour|farin)/', 'driver' => 'cpi.bread_cereals', 'category' => CostCategory::Ingredients, 'line' => 'Flour', 'confidence' => 0.8],
        ['pattern' => '/\b(vaj|luledielli|sunflower|cooking oil)/', 'driver' => 'cpi.oils_fats', 'category' => CostCategory::Ingredients, 'line' => 'Cooking oil', 'confidence' => 0.85],
        ['pattern' => '/\b(sheqer|sugar)/', 'driver' => 'cpi.sugar', 'category' => CostCategory::Ingredients, 'line' => 'Sugar', 'confidence' => 0.9],
        ['pattern' => '/\b(gjalp|qumesht|djath|ajk|butter|milk|cheese|cream|dairy)/', 'driver' => 'cpi.milk_cheese_eggs', 'category' => CostCategory::Ingredients, 'line' => 'Dairy', 'confidence' => 0.85],
        ['pattern' => '/\b(veze|eggs?)\b/', 'driver' => 'cpi.milk_cheese_eggs', 'category' => CostCategory::Ingredients, 'line' => 'Eggs', 'confidence' => 0.8],
        ['pattern' => '/\b(rrym|elektr|kesco|keds|electric|kwh)/', 'driver' => 'cpi.electricity_gas', 'category' => CostCategory::Energy, 'line' => 'Electricity', 'confidence' => 0.9],
        ['pattern' => '/\b(paga|page|rrog|wage|salar|payroll)/', 'driver' => 'wages.kosovo', 'category' => CostCategory::Wages, 'line' => 'Wages', 'confidence' => 0.9],
        ['pattern' => '/\b(qira|qeraj|rent\b)/', 'driver' => null, 'category' => CostCategory::Rent, 'line' => 'Rent', 'confidence' => 0.8],
        ['pattern' => '/\b(paketim|qese|kuti|karton|packag|bags?|box)/', 'driver' => 'cpi.headline', 'category' => CostCategory::Packaging, 'line' => 'Packaging', 'confidence' => 0.6],
        ['pattern' => '/\b(transport|dergese|delivery|shipping|freight)/', 'driver' => 'cpi.transport_services', 'category' => CostCategory::Transport, 'line' => 'Transport services', 'confidence' => 0.7],
        ['pattern' => '/\b(maja|kripe|yeast|salt|ushqim|food)/', 'driver' => 'cpi.food', 'category' => CostCategory::Ingredients, 'line' => 'Other ingredients', 'confidence' => 0.6],
    ],

    /*
    |--------------------------------------------------------------------------
    | Historical stress scenarios
    |--------------------------------------------------------------------------
    |
    | "Replay 2022" applies the month-by-month changes each driver actually had
    | from March 2022 (taken from its own ASK history) to today's cost base.
    | Replayed history is shown with the "assumption" label.
    |
    */

    'scenarios' => [
        'replay_2022' => [
            'name' => 'Replay 2022 (actual ASK changes from March 2022)',
            'from' => '2022-03',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Kosovo Agency of Statistics (ASKdata PxWeb API)
    |--------------------------------------------------------------------------
    |
    | Price drivers imported from askdata.rks-gov.net by `margin:import-ask`.
    | Index levels are chained from monthly changes, so ASK rebasing the index
    | (as in April 2026) does not break the series. The import also writes a
    | snapshot of the real data that the seeder uses offline.
    |
    */

    'ask' => [
        'base_url' => env('ASK_API_URL', 'https://askdata.rks-gov.net/api/v1/en/ASKdata'),
        'months' => 60,
        'snapshot_path' => database_path('data/ask-snapshot.json'),
        'source' => 'Kosovo Agency of Statistics (ASKdata)',
        'headline' => [
            'code' => 'cpi.headline',
            'name' => 'HICP, all items',
            'table' => 'Prices/Consumer Price Index/Monthly indicators/cpi01.px',
            'time_dimension' => 'Viti/muaji',
            'variable_dimension' => 'variabla',
            'level' => '0',
            'monthly_change' => '1',
            'annual_change' => '2',
        ],
        'subgroups' => [
            'levels_table' => 'Prices/Consumer Price Index/Monthly indicators/cpi09.px',
            'changes_table' => 'Prices/Consumer Price Index/Monthly indicators/cpi05.px',
            'time_dimension' => 'Viti/muaji',
            'group_dimension' => 'Grupet dhe nëngrupet',
            'series' => [
                'cpi.food' => ['item' => '2', 'name' => 'HICP 01.1 Food', 'kind' => 'cpi'],
                'cpi.bread_cereals' => ['item' => '3', 'name' => 'HICP 01.1.1 Bread and cereals', 'kind' => 'cpi'],
                'cpi.milk_cheese_eggs' => ['item' => '6', 'name' => 'HICP 01.1.4 Milk, cheese and eggs', 'kind' => 'cpi'],
                'cpi.oils_fats' => ['item' => '7', 'name' => 'HICP 01.1.5 Oils and fats', 'kind' => 'cpi'],
                'cpi.sugar' => ['item' => '10', 'name' => 'HICP 01.1.8 Sugar, jam, honey, chocolate', 'kind' => 'cpi'],
                'cpi.coffee_tea_cocoa' => ['item' => '13', 'name' => 'HICP 01.2.1 Coffee, tea and cocoa', 'kind' => 'cpi'],
                'cpi.soft_drinks' => ['item' => '14', 'name' => 'HICP 01.2.2 Mineral waters, soft drinks and juices', 'kind' => 'cpi'],
                'cpi.housing_energy' => ['item' => '21', 'name' => 'HICP 04 Housing, water, electricity, gas and other fuels', 'kind' => 'cpi'],
                'cpi.electricity_gas' => ['item' => '25', 'name' => 'HICP 04.5 Electricity, gas and other fuels', 'kind' => 'energy'],
                'cpi.transport' => ['item' => '37', 'name' => 'HICP 07 Transport', 'kind' => 'cpi'],
                'cpi.transport_fuel' => ['item' => '39', 'name' => 'HICP 07.2 Operation of personal transport (fuels)', 'kind' => 'fuel'],
                'cpi.transport_services' => ['item' => '40', 'name' => 'HICP 07.3 Transport services', 'kind' => 'cpi'],
                'cpi.catering' => ['item' => '54', 'name' => 'HICP 11.1 Catering services', 'kind' => 'cpi'],
            ],
        ],
        'wages' => [
            'code' => 'wages.kosovo',
            'name' => 'Average gross salary, Kosovo (annual)',
            'table' => 'Labour market/Niveli i Pagave/tab01.px',
            'year_dimension' => 'Viti',
            'variable_dimension' => 'Variabla',
            'variable' => '0',
            'gross_dimension' => 'Bruto/neto',
            'gross' => '0',
        ],
    ],

];
