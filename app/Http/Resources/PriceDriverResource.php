<?php

namespace App\Http\Resources;

use App\Enums\StatementLabel;
use App\Models\PriceDriver;
use App\Services\Margin\LabelledValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PriceDriver
 */
class PriceDriverResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $observations = $this->observations->sortBy('period')->values();
        $latest = $observations->last();
        $yearAgo = $latest === null ? null : $observations->first(fn ($observation) => $observation->period->equalTo($latest->period->copy()->subYear()));
        $projection = $this->projections->sortByDesc('horizon_months')->first();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'kind' => $this->kind->value,
            'source' => $this->source,
            'latest' => $latest === null ? null : [
                'period' => $latest->period->format('Y-m'),
                'value' => $latest->value,
                'change_last_12_months' => $yearAgo === null ? null : LabelledValue::percent($latest->value / $yearAgo->value - 1, StatementLabel::Data, source: $this->source),
            ],
            'projection' => $projection === null ? null : [
                'horizon_months' => $projection->horizon_months,
                'change' => LabelledValue::percent($projection->change_mid, StatementLabel::Forecast, $projection->change_low, $projection->change_high, source: $projection->source),
                'published_on' => $projection->published_on->toDateString(),
            ],
            'history' => $observations->map(fn ($observation): array => [
                'period' => $observation->period->format('Y-m'),
                'value' => $observation->value,
            ]),
        ];
    }
}
