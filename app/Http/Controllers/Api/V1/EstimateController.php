<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Margin\QuickEstimator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Your business": the owner's own monthly numbers run through the engine with official ASK series.
 */
class EstimateController extends Controller
{
    public function store(Request $request, QuickEstimator $estimator): JsonResponse
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:100000000'];

        $validated = $request->validate([
            'industry' => ['required', Rule::in(array_keys(QuickEstimator::IndustryGoodsDriver))],
            'sales' => ['required', 'numeric', 'min:1', 'max:100000000'],
            'goods' => ['required', ...array_slice($money, 1)],
            'salaries' => ['required', ...array_slice($money, 1)],
            'rent' => ['required', ...array_slice($money, 1)],
            'utilities' => ['required', ...array_slice($money, 1)],
            'fuel' => $money,
            'other' => $money,
            'cash' => ['required', ...array_slice($money, 1)],
            'drawings' => $money,
            'min_cash' => $money,
            'loan' => $money,
            'loan_rate' => ['nullable', 'numeric', 'between:0,100'],
            'salary_rise' => ['nullable', 'numeric', 'between:-50,100'],
            'rent_rise' => ['nullable', 'numeric', 'between:-50,100'],
            'products' => ['sometimes', 'array', 'max:20'],
            'products.*.name' => ['required', 'string', 'max:100'],
            'products.*.price' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        return response()->json(['data' => $estimator->estimate($validated)]);
    }
}
