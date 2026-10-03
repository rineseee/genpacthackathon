<?php

namespace App\Models;

use App\Enums\CostCategory;
use App\Enums\MappingStatus;
use Database\Factories\CostLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One thing the company spends on (coffee, electricity, wages), linked to the public price driver it follows.
 */
#[Fillable(['company_id', 'price_driver_id', 'name', 'category', 'unit', 'scales_with_volume', 'storable', 'storage_cost_rate', 'mapping_status', 'mapping_confidence'])]
class CostLine extends Model
{
    /** @use HasFactory<CostLineFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => CostCategory::class,
            'mapping_status' => MappingStatus::class,
            'scales_with_volume' => 'boolean',
            'storable' => 'boolean',
            'storage_cost_rate' => 'float',
            'mapping_confidence' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<PriceDriver, $this>
     */
    public function priceDriver(): BelongsTo
    {
        return $this->belongsTo(PriceDriver::class);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * @return HasMany<SupplierOffer, $this>
     */
    public function supplierOffers(): HasMany
    {
        return $this->hasMany(SupplierOffer::class);
    }
}
