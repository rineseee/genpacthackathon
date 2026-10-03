<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Margin\MarginRadar;
use Illuminate\Http\JsonResponse;

/**
 * Every candidate response with its euro value, and the combined plan, each simulated against doing nothing.
 */
class RecommendationController extends Controller
{
    public function index(Company $company, MarginRadar $radar): JsonResponse
    {
        $analysis = $radar->analyse($company);

        return response()->json(['data' => ['as_of' => $analysis['as_of'], ...$analysis['recommendations']]]);
    }
}
