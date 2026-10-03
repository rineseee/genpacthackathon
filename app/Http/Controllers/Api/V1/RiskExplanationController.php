<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Margin\MarginRadar;
use Illuminate\Http\JsonResponse;

/**
 * "Ask why": what drives the company's cash-stress probability, with every figure from the engine.
 */
class RiskExplanationController extends Controller
{
    public function show(Company $company, MarginRadar $radar): JsonResponse
    {
        $analysis = $radar->analyse($company);

        return response()->json(['data' => ['as_of' => $analysis['as_of'], ...$analysis['risk_explanation']]]);
    }
}
