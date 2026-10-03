<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'industry', 'locations', 'currency', 'cash_balance', 'minimum_cash_reserve', 'monthly_non_operating_outflows', 'price_elasticity'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locations' => 'integer',
            'cash_balance' => 'float',
            'minimum_cash_reserve' => 'float',
            'monthly_non_operating_outflows' => 'float',
            'price_elasticity' => 'float',
        ];
    }

    /**
     * @return HasMany<Supplier, $this>
     */
    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    /**
     * @return HasMany<CostLine, $this>
     */
    public function costLines(): HasMany
    {
        return $this->hasMany(CostLine::class);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * @return HasMany<MonthlyFinancial, $this>
     */
    public function monthlyFinancials(): HasMany
    {
        return $this->hasMany(MonthlyFinancial::class);
    }

    /**
     * @return HasMany<SupplierOffer, $this>
     */
    public function supplierOffers(): HasMany
    {
        return $this->hasMany(SupplierOffer::class);
    }

    /**
     * @return HasMany<AlertRule, $this>
     */
    public function alertRules(): HasMany
    {
        return $this->hasMany(AlertRule::class);
    }

    /**
     * @return HasMany<SimulationRun, $this>
     */
    public function simulationRuns(): HasMany
    {
        return $this->hasMany(SimulationRun::class);
    }
}
