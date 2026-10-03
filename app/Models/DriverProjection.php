<?php

namespace App\Models;

use Database\Factories\DriverProjectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A borrowed outside projection (central bank, futures market) of how much a driver changes over a horizon.
 */
#[Fillable(['price_driver_id', 'horizon_months', 'change_low', 'change_mid', 'change_high', 'source', 'published_on'])]
class DriverProjection extends Model
{
    /** @use HasFactory<DriverProjectionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'horizon_months' => 'integer',
            'change_low' => 'float',
            'change_mid' => 'float',
            'change_high' => 'float',
            'published_on' => 'date',
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
