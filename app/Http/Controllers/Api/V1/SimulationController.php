<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSimulationRequest;
use App\Http\Resources\SimulationRunResource;
use App\Models\Company;
use App\Models\SimulationRun;
use App\Services\Margin\ScenarioRunner;

/**
 * What-if questions and stress tests ("replay 2022", "fuel +20%", "raise prices 3%").
 */
class SimulationController extends Controller
{
    public function store(StoreSimulationRequest $request, Company $company, ScenarioRunner $runner): SimulationRunResource
    {
        return new SimulationRunResource($runner->run($company, $request->validated()));
    }

    public function show(Company $company, SimulationRun $simulationRun): SimulationRunResource
    {
        return new SimulationRunResource($simulationRun);
    }
}
