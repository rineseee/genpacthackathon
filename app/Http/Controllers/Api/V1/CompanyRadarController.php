<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Margin\MarginRadar;
use Illuminate\Http\JsonResponse;

/**
 * The dashboard headline: profit lost to inflation, margin at risk, do-nothing versus plan.
 */
class CompanyRadarController extends Controller
{
    public function show(Company $company, MarginRadar $radar): JsonResponse
    {
        $analysis = $radar->analyse($company);

        return response()->json(['data' => [
            'company' => $analysis['company'],
            'as_of' => $analysis['as_of'],
            ...$analysis['overview'],
        ]]);
    }
}
