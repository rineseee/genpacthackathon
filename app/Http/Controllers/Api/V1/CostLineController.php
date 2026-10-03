<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MappingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCostLineRequest;
use App\Http\Resources\CostLineResource;
use App\Models\Company;
use App\Models\CostLine;
use App\Services\Margin\MarginRadar;
use Illuminate\Http\JsonResponse;

class CostLineController extends Controller
{
    /**
     * Cost lines with their driver, learned pass-through, last-year inflation and 3-month forecast range.
     */
    public function index(Company $company, MarginRadar $radar): JsonResponse
    {
        return response()->json(['data' => $radar->analyse($company)['cost_lines']]);
    }

    /**
     * The owner confirms or corrects a mapping. Any correction by the owner counts as confirmed.
     */
    public function update(UpdateCostLineRequest $request, Company $company, CostLine $costLine): CostLineResource
    {
        $attributes = $request->validated();

        if (array_intersect_key($attributes, array_flip(['price_driver_id', 'category'])) !== [] || ($attributes['mapping_status'] ?? null) === MappingStatus::Confirmed->value) {
            $attributes['mapping_status'] = MappingStatus::Confirmed;
            $attributes['mapping_confidence'] = 1;
        }

        $costLine->update($attributes);

        return new CostLineResource($costLine->load('priceDriver'));
    }
}
