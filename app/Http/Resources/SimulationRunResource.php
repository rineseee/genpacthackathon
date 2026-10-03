<?php

namespace App\Http\Resources;

use App\Models\SimulationRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SimulationRun
 */
class SimulationRunResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scenario' => $this->scenario,
            'seed' => $this->seed,
            'paths' => $this->paths,
            'horizon_months' => $this->horizon_months,
            'input' => $this->input,
            'result' => $this->result,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
