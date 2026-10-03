<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
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
            'industry' => $this->industry,
            'locations' => $this->locations,
            'currency' => $this->currency,
            'cash_balance' => $this->cash_balance,
            'minimum_cash_reserve' => $this->minimum_cash_reserve,
            'monthly_non_operating_outflows' => $this->monthly_non_operating_outflows,
            'price_elasticity' => $this->price_elasticity,
        ];
    }
}
