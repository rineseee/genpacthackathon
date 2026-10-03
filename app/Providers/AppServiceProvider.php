<?php

namespace App\Providers;

use App\Services\Margin\Classification\ExpenseClassifier;
use App\Services\Margin\Classification\KeywordExpenseClassifier;
use App\Services\Margin\Simulation\MonteCarloSimulator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ExpenseClassifier::class, KeywordExpenseClassifier::class);

        // One instance per request or job, so simulations of the same futures reuse the generated paths.
        $this->app->scoped(MonteCarloSimulator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
