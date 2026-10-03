<?php

namespace App\Models;

use App\Enums\OfferKind;
use Database\Factories\SupplierOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A quote the owner has received: a cheaper alternative supplier, or a fixed-price contract.
 */
#[Fillable(['company_id', 'supplier_id', 'cost_line_id', 'kind', 'unit_price', 'max_share', 'duration_months', 'quoted_on'])]
class SupplierOffer extends Model
{
    /** @use HasFactory<SupplierOfferFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => OfferKind::class,
            'unit_price' => 'float',
            'max_share' => 'float',
            'duration_months' => 'integer',
            'quoted_on' => 'date',
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
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<CostLine, $this>
     */
    public function costLine(): BelongsTo
    {
        return $this->belongsTo(CostLine::class);
    }
}
