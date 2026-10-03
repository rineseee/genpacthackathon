<?php

namespace App\Models;

use App\Enums\DriverKind;
use Database\Factories\PriceDriverFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A public price series a cost line can follow: a CPI category, a commodity, energy, fuel, wages or FX.
 */
#[Fillable(['code', 'name', 'kind', 'source', 'unit'])]
class PriceDriver extends Model
{
    /** @use HasFactory<PriceDriverFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DriverKind::class,
        ];
    }

    /**
     * @return HasMany<PriceObservation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(PriceObservation::class);
    }

    /**
     * @return HasMany<DriverProjection, $this>
     */
    public function projections(): HasMany
    {
        return $this->hasMany(DriverProjection::class);
    }
}
