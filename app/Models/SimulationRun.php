<?php

namespace App\Models;

use Database\Factories\SimulationRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stored simulation, kept so every number shown can be reproduced from its seed and input.
 */
#[Fillable(['company_id', 'scenario', 'seed', 'paths', 'horizon_months', 'input', 'result'])]
class SimulationRun extends Model
{
    /** @use HasFactory<SimulationRunFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'paths' => 'integer',
            'horizon_months' => 'integer',
            'input' => 'array',
            'result' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
