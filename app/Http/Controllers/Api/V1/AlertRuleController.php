<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAlertRuleRequest;
use App\Http\Requests\UpdateAlertRuleRequest;
use App\Http\Resources\AlertRuleResource;
use App\Models\AlertRule;
use App\Models\Company;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Owner-set limits. Delivery of the alert emails is handled separately (margin:check-alerts).
 */
class AlertRuleController extends Controller
{
    public function index(Company $company): AnonymousResourceCollection
    {
        return AlertRuleResource::collection($company->alertRules()->orderBy('id')->get());
    }

    public function store(StoreAlertRuleRequest $request, Company $company): AlertRuleResource
    {
        return new AlertRuleResource($company->alertRules()->create($request->validated()));
    }

    public function update(UpdateAlertRuleRequest $request, Company $company, AlertRule $alertRule): AlertRuleResource
    {
        $alertRule->update($request->validated());

        return new AlertRuleResource($alertRule);
    }

    public function destroy(Company $company, AlertRule $alertRule): Response
    {
        $alertRule->delete();

        return response()->noContent();
    }
}
