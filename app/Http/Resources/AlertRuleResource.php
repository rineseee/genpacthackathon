<?php

namespace App\Http\Resources;

use App\Models\AlertRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AlertRule
 */
class AlertRuleResource extends JsonResource
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
            'metric' => $this->metric->value,
            'threshold' => $this->threshold,
            'recipient_email' => $this->recipient_email,
            'last_triggered_at' => $this->last_triggered_at?->toIso8601String(),
        ];
    }
}
