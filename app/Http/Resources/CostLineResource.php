<?php

namespace App\Http\Resources;

use App\Enums\MappingStatus;
use App\Enums\StatementLabel;
use App\Models\CostLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CostLine
 */
class CostLineResource extends JsonResource
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
            'name' => $this->name,
            'category' => $this->category->value,
            'unit' => $this->unit,
            'scales_with_volume' => $this->scales_with_volume,
            'storable' => $this->storable,
            'storage_cost_rate' => $this->storage_cost_rate,
            'driver' => $this->whenLoaded('priceDriver', fn () => $this->priceDriver?->only(['id', 'code', 'name', 'kind', 'source'])),
            'mapping' => [
                'status' => $this->mapping_status->value,
                'confidence' => $this->mapping_confidence,
                'label' => $this->mapping_status === MappingStatus::Confirmed ? StatementLabel::Data->value : StatementLabel::NeedsValidation->value,
            ],
        ];
    }
}
