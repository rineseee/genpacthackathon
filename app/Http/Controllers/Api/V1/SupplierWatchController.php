<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Margin\MarginRadar;
use Illuminate\Http\JsonResponse;

/**
 * Suppliers raising prices faster than their market, with the evidence and a draft renegotiation message.
 */
class SupplierWatchController extends Controller
{
    public function index(Company $company, MarginRadar $radar): JsonResponse
    {
        return response()->json(['data' => $radar->analyse($company)['supplier_watch']]);
    }
}
