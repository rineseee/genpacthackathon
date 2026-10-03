<?php

namespace App\Http\Requests;

use App\Enums\CostCategory;
use App\Enums\MappingStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The owner confirms or corrects how a cost line is mapped to its price driver.
 */
class UpdateCostLineRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'price_driver_id' => ['sometimes', 'nullable', 'integer', Rule::exists('price_drivers', 'id')],
            'category' => ['sometimes', Rule::enum(CostCategory::class)],
            'mapping_status' => ['sometimes', Rule::enum(MappingStatus::class)],
            'scales_with_volume' => ['sometimes', 'boolean'],
            'storable' => ['sometimes', 'boolean'],
            'storage_cost_rate' => ['sometimes', 'numeric', 'between:0,0.2'],
        ];
    }
}
