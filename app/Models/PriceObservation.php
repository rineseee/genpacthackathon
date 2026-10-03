<?php

namespace App\Models;

use Database\Factories\PriceObservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['price_driver_id', 'period', 'value'])]
class PriceObservation extends Model
{
    /** @use HasFactory<PriceObservationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => 'date',
            'value' => 'float',
        ];
    }

    /**
     * @return BelongsTo<PriceDriver, $this>
     */
    public function priceDriver(): BelongsTo
    {
        return $this->belongsTo(PriceDriver::class);
    }
}
