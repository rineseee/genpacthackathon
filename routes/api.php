<?php

use App\Http\Controllers\Api\V1\AlertRuleController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\CompanyRadarController;
use App\Http\Controllers\Api\V1\CostLineController;
use App\Http\Controllers\Api\V1\EstimateController;
use App\Http\Controllers\Api\V1\ExpenseClassificationController;
use App\Http\Controllers\Api\V1\InvoiceImportController;
use App\Http\Controllers\Api\V1\PriceDriverController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\RiskExplanationController;
use App\Http\Controllers\Api\V1\SimulationController;
use App\Http\Controllers\Api\V1\SupplierWatchController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('price-drivers', [PriceDriverController::class, 'index'])->name('price-drivers.index');
    Route::post('estimates', [EstimateController::class, 'store'])->name('estimates.store');

    Route::apiResource('companies', CompanyController::class)->only(['index', 'show']);

    Route::prefix('companies/{company}')->name('companies.')->scopeBindings()->group(function () {
        Route::get('radar', [CompanyRadarController::class, 'show'])->name('radar.show');
        Route::get('recommendations', [RecommendationController::class, 'index'])->name('recommendations.index');
        Route::get('supplier-watch', [SupplierWatchController::class, 'index'])->name('supplier-watch.index');
        Route::get('risk-explanation', [RiskExplanationController::class, 'show'])->name('risk-explanation.show');

        Route::apiResource('cost-lines', CostLineController::class)->only(['index', 'update']);
        Route::post('expense-classifications', [ExpenseClassificationController::class, 'store'])->name('expense-classifications.store');
        Route::post('invoice-imports', [InvoiceImportController::class, 'store'])->name('invoice-imports.store');

        Route::apiResource('simulations', SimulationController::class)->only(['store', 'show'])
            ->parameters(['simulations' => 'simulationRun']);

        Route::apiResource('alert-rules', AlertRuleController::class)->except(['show']);
    });
});
