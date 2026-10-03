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
        ['pattern' => '/\b(nafte|dizel|diesel|karburant|benzin|fuel|gasoline)/', 'driver' => 'fuel.diesel', 'category' => CostCategory::Transport, 'line' => 'Fuel', 'confidence' => 0.9],
        ['pattern' => '/\b(miell|mielli|flour|farin)/', 'driver' => 'commodity.wheat', 'category' => CostCategory::Ingredients, 'line' => 'Flour', 'confidence' => 0.9],
        ['pattern' => '/\b(vaj|luledielli|sunflower|cooking oil)/', 'driver' => 'commodity.sunflower_oil', 'category' => CostCategory::Ingredients, 'line' => 'Cooking oil', 'confidence' => 0.85],
        ['pattern' => '/\b(sheqer|sugar)/', 'driver' => 'commodity.sugar', 'category' => CostCategory::Ingredients, 'line' => 'Sugar', 'confidence' => 0.9],
        ['pattern' => '/\b(gjalp|qumesht|djath|ajk|butter|milk|cheese|cream|dairy)/', 'driver' => 'commodity.dairy', 'category' => CostCategory::Ingredients, 'line' => 'Dairy', 'confidence' => 0.85],
        ['pattern' => '/\b(veze|eggs?)\b/', 'driver' => 'cpi.food', 'category' => CostCategory::Ingredients, 'line' => 'Eggs', 'confidence' => 0.8],
        ['pattern' => '/\b(rrym|elektr|kesco|keds|electric|kwh)/', 'driver' => 'energy.electricity', 'category' => CostCategory::Energy, 'line' => 'Electricity', 'confidence' => 0.9],
        ['pattern' => '/\b(paga|page|rrog|wage|salar|payroll)/', 'driver' => 'wages.kosovo', 'category' => CostCategory::Wages, 'line' => 'Wages', 'confidence' => 0.9],
        ['pattern' => '/\b(qira|qeraj|rent\b)/', 'driver' => null, 'category' => CostCategory::Rent, 'line' => 'Rent', 'confidence' => 0.8],
        ['pattern' => '/\b(paketim|qese|kuti|karton|packag|bags?|box)/', 'driver' => 'cpi.headline', 'category' => CostCategory::Packaging, 'line' => 'Packaging', 'confidence' => 0.6],
        ['pattern' => '/\b(transport|dergese|delivery|shipping|freight)/', 'driver' => 'cpi.transport', 'category' => CostCategory::Transport, 'line' => 'Transport services', 'confidence' => 0.7],
        ['pattern' => '/\b(maja|kripe|yeast|salt|ushqim|food)/', 'driver' => 'cpi.food', 'category' => CostCategory::Ingredients, 'line' => 'Other ingredients', 'confidence' => 0.6],
    ],

    /*
    |--------------------------------------------------------------------------
    | Historical stress scenarios
    |--------------------------------------------------------------------------
    |
    | Monthly driver changes replayed in place of the projected drift. These are
    | approximate shapes of the 2022 shock, not exact history, and are always
    | shown with the "assumption" label.
    |
    */

    'scenarios' => [
        'replay_2022' => [
            'name' => 'Replay 2022 (approximate shock profile)',
            'monthly_changes' => [
                'commodity.wheat' => [0.20, 0.10, 0.05, -0.05, -0.08, -0.02],
                'commodity.sunflower_oil' => [0.30, 0.15, 0.05, -0.05, -0.10, -0.05],
                'commodity.sugar' => [0.05, 0.03, 0.02, 0.00, 0.00, 0.01],
                'commodity.dairy' => [0.04, 0.04, 0.04, 0.03, 0.03, 0.02],
                'energy.electricity' => [0.10, 0.05, 0.05, 0.05, 0.05, 0.05],
                'fuel.diesel' => [0.25, 0.05, 0.05, 0.02, -0.05, -0.05],
                'wages.kosovo' => [0.01, 0.01, 0.01, 0.01, 0.01, 0.01],
                'cpi.headline' => [0.015, 0.015, 0.015, 0.015, 0.01, 0.01],
                'cpi.food' => [0.02, 0.02, 0.02, 0.02, 0.015, 0.015],
                'cpi.transport' => [0.05, 0.03, 0.02, 0.01, 0.00, 0.00],
                'cpi.housing_energy' => [0.03, 0.03, 0.02, 0.02, 0.02, 0.02],
            ],
        ],
    ],

];
