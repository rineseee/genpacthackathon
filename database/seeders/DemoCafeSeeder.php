<?php

namespace Database\Seeders;

use App\Enums\CostCategory;
use App\Enums\MappingStatus;
use App\Enums\OfferKind;
use App\Models\AlertRule;
use App\Models\Company;
use App\Models\CostLine;
use App\Models\PriceDriver;
use App\Services\Margin\MonthlySeries;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo company: a fictional three-location cafe with 18 months of invoices and sales.
 *
 * The company and its invoices are fictional; the price drivers are real Kosovo Agency of Statistics
 * series. Invoice prices follow those series with a known pass-through and lag, plus noise, so the
 * engine has a real link to learn. The milk supplier quietly adds a markup over the last months,
 * which the supplier watch should catch. Requires PriceDriverSeeder.
 */
class DemoCafeSeeder extends Seeder
{
    private const string AsOf = '2026-09';

    private const int HistoryMonths = 18;

    private const float MonthlyRevenue = 62000;

    /**
     * Today's monthly spend and unit price per line, and how its price follows its driver.
     *
     * @var list<array{name: string, description: string, category: CostCategory, unit: string, driver: string|null, pass_through: float, lag: int, spend: float, unit_price: float, supplier: string|null, storable?: bool, storage_cost_rate?: float, invoices_per_month: int, status?: MappingStatus, confidence?: float}>
     */
    private const array Lines = [
        ['name' => 'Coffee beans', 'description' => 'Kafe kokërr 1kg', 'category' => CostCategory::Ingredients, 'unit' => 'kg', 'driver' => 'cpi.coffee_tea_cocoa', 'pass_through' => 1.0, 'lag' => 1, 'spend' => 9000, 'unit_price' => 18.00, 'supplier' => 'Kafe Fusha', 'storable' => true, 'storage_cost_rate' => 0.01, 'invoices_per_month' => 4],
        ['name' => 'Milk', 'description' => 'Qumësht 3.2% 1L', 'category' => CostCategory::Ingredients, 'unit' => 'l', 'driver' => 'cpi.milk_cheese_eggs', 'pass_through' => 1.0, 'lag' => 0, 'spend' => 4500, 'unit_price' => 1.20, 'supplier' => 'Qumështorja Prishtina', 'invoices_per_month' => 4],
        ['name' => 'Sugar', 'description' => 'Sheqer kristal 1kg', 'category' => CostCategory::Ingredients, 'unit' => 'kg', 'driver' => 'cpi.sugar', 'pass_through' => 0.8, 'lag' => 1, 'spend' => 1200, 'unit_price' => 1.20, 'supplier' => 'Furnizime Dardana', 'storable' => true, 'storage_cost_rate' => 0.005, 'invoices_per_month' => 2],
        ['name' => 'Syrups and chocolate', 'description' => 'Shurup dhe çokollatë për pije', 'category' => CostCategory::Ingredients, 'unit' => 'bottle', 'driver' => 'cpi.sugar', 'pass_through' => 0.8, 'lag' => 1, 'spend' => 2500, 'unit_price' => 8.00, 'supplier' => 'Furnizime Dardana', 'invoices_per_month' => 2],
        ['name' => 'Pastries', 'description' => 'Kroasanë dhe ëmbëlsira', 'category' => CostCategory::Ingredients, 'unit' => 'pack', 'driver' => 'cpi.bread_cereals', 'pass_through' => 1.0, 'lag' => 0, 'spend' => 3500, 'unit_price' => 12.00, 'supplier' => 'Pastiçeria Qyteti', 'invoices_per_month' => 4],
        ['name' => 'Packaging', 'description' => 'Gota dhe kapakë për kafe', 'category' => CostCategory::Packaging, 'unit' => 'pack', 'driver' => 'cpi.headline', 'pass_through' => 1.0, 'lag' => 1, 'spend' => 1700, 'unit_price' => 12.00, 'supplier' => 'Furnizime Dardana', 'storable' => true, 'storage_cost_rate' => 0.005, 'invoices_per_month' => 1, 'status' => MappingStatus::Suggested, 'confidence' => 0.6],
        ['name' => 'Electricity', 'description' => 'Rryma - fatura mujore e 3 lokaleve', 'category' => CostCategory::Energy, 'unit' => 'kWh', 'driver' => 'cpi.electricity_gas', 'pass_through' => 1.0, 'lag' => 0, 'spend' => 5000, 'unit_price' => 0.11, 'supplier' => 'Energjia Demo', 'invoices_per_month' => 1],
        ['name' => 'Delivery fuel', 'description' => 'Naftë për furnizime dhe dërgesa', 'category' => CostCategory::Transport, 'unit' => 'l', 'driver' => 'cpi.transport_fuel', 'pass_through' => 1.0, 'lag' => 0, 'spend' => 600, 'unit_price' => 1.45, 'supplier' => 'Pika e Karburantit', 'invoices_per_month' => 2],
        ['name' => 'Wages', 'description' => 'Pagat e stafit në 3 lokale', 'category' => CostCategory::Wages, 'unit' => 'month', 'driver' => 'wages.kosovo', 'pass_through' => 1.0, 'lag' => 0, 'spend' => 19000, 'unit_price' => 19000, 'supplier' => null, 'invoices_per_month' => 1],
        ['name' => 'Rent', 'description' => 'Qiraja e 3 lokaleve', 'category' => CostCategory::Rent, 'unit' => 'month', 'driver' => null, 'pass_through' => 0.0, 'lag' => 0, 'spend' => 6000, 'unit_price' => 6000, 'supplier' => 'Pronari i lokaleve', 'invoices_per_month' => 1],
        ['name' => 'Other ingredients', 'description' => 'Çaj, ujë dhe përbërës të tjerë', 'category' => CostCategory::Ingredients, 'unit' => 'pack', 'driver' => 'cpi.food', 'pass_through' => 1.0, 'lag' => 0, 'spend' => 1000, 'unit_price' => 9.00, 'supplier' => 'Furnizime Dardana', 'invoices_per_month' => 1],
    ];

    /**
     * Extra markup the milk supplier adds on top of the benchmark, by month.
     *
     * @var array<string, float>
     */
    private const array MilkMarkup = ['2026-06' => 1.04, '2026-07' => 1.08, '2026-08' => 1.11, '2026-09' => 1.115];

    /**
     * Own selling-price index: one 2% rise in November 2025 and 1.5% in June 2026.
     *
     * @var array<string, float>
     */
    private const array PriceSteps = ['2025-11' => 1.02, '2026-06' => 1.015];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        mt_srand(11);

        Company::query()->where('name', 'Cafe Demo')->delete();

        $company = Company::create([
            'name' => 'Cafe Demo',
            'industry' => 'cafe',
            'locations' => 3,
            'currency' => 'EUR',
            'cash_balance' => 20000,
            'minimum_cash_reserve' => 15000,
            'monthly_non_operating_outflows' => 7000,
            'price_elasticity' => -0.5,
        ]);

        $drivers = PriceDriver::query()->with('observations')->get()->keyBy('code');
        $driverSeries = $drivers->map(fn (PriceDriver $driver): array => $driver->observations->mapWithKeys(
            fn ($observation): array => [MonthlySeries::key($observation->period->toImmutable()) => $observation->value],
        )->all());

        $periods = MonthlySeries::window(self::AsOf, self::HistoryMonths);
        $suppliers = [];
        $costLines = [];

        foreach (self::Lines as $definition) {
            $costLine = $company->costLines()->create([
                'price_driver_id' => $definition['driver'] === null ? null : $drivers[$definition['driver']]->id,
                'name' => $definition['name'],
                'category' => $definition['category'],
                'unit' => $definition['unit'],
                'scales_with_volume' => $definition['category']->scalesWithVolume(),
                'storable' => $definition['storable'] ?? false,
                'storage_cost_rate' => $definition['storage_cost_rate'] ?? 0,
                'mapping_status' => $definition['status'] ?? MappingStatus::Confirmed,
                'mapping_confidence' => $definition['confidence'] ?? 1,
            ]);
            $costLines[$definition['name']] = $costLine;

            $supplierId = null;

            if ($definition['supplier'] !== null) {
                $suppliers[$definition['supplier']] ??= $company->suppliers()->create(['name' => $definition['supplier']]);
                $supplierId = $suppliers[$definition['supplier']]->id;
            }

            $this->seedInvoices($company, $costLine, $supplierId, $definition, $periods, $driverSeries[$definition['driver']] ?? []);
        }

        $this->seedFinancials($company, $periods);
        $this->seedOffers($company, $costLines);

        AlertRule::create([
            'company_id' => $company->id,
            'metric' => 'margin_at_risk',
            'threshold' => 500,
            'recipient_email' => 'owner@cafe-demo.test',
        ]);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  list<string>  $periods
     * @param  array<string, float>  $driverSeries
     */
    private function seedInvoices(Company $company, CostLine $costLine, ?int $supplierId, array $definition, array $periods, array $driverSeries): void
    {
        $lag = $definition['lag'];
        $anchor = MonthlySeries::valueAtOrBefore($driverSeries, MonthlySeries::shift(self::AsOf, -$lag));
        $monthlyQuantity = $definition['spend'] / $definition['unit_price'];
        $latestMarkup = $definition['name'] === 'Milk' ? self::MilkMarkup[self::AsOf] : 1.0;
        $rows = [];

        foreach ($periods as $index => $period) {
            $driverValue = $anchor === null ? null : MonthlySeries::valueAtOrBefore($driverSeries, MonthlySeries::shift($period, -$lag));
            $marketFactor = $driverValue === null ? 1.0 : 1 + $definition['pass_through'] * ($driverValue / $anchor - 1);
            $markup = $definition['name'] === 'Milk' ? (self::MilkMarkup[$period] ?? 1.0) / $latestMarkup : 1.0;
            $isLatest = $period === self::AsOf;
            $noise = $isLatest || $definition['driver'] === null ? 1.0 : 1 + $this->noise(0.004);
            $unitPrice = round($definition['unit_price'] * $marketFactor * $markup * $noise, 4);
            $isBaseline = $index >= count($periods) - 3;
            $quantity = $monthlyQuantity * ($isBaseline ? 1.0 : 1 + $this->noise(0.04));
            $month = CarbonImmutable::createFromFormat('!Y-m', $period);

            for ($invoice = 0; $invoice < $definition['invoices_per_month']; $invoice++) {
                $invoiceQuantity = round($quantity / $definition['invoices_per_month'], 3);
                $rows[] = [
                    'company_id' => $company->id,
                    'supplier_id' => $supplierId,
                    'cost_line_id' => $costLine->id,
                    'invoiced_on' => $month->addDays(min(27, 2 + $invoice * 7))->toDateString(),
                    'description' => $definition['description'],
                    'quantity' => $invoiceQuantity,
                    'unit' => $definition['unit'],
                    'unit_price' => $unitPrice,
                    'total' => round($invoiceQuantity * $unitPrice, 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        $company->invoiceLines()->insert($rows);
    }

    /**
     * @param  list<string>  $periods
     */
    private function seedFinancials(Company $company, array $periods): void
    {
        $index = 100.0;
        $indexByPeriod = [];

        foreach ($periods as $period) {
            $index *= self::PriceSteps[$period] ?? 1.0;
            $indexByPeriod[$period] = $index;
        }

        foreach ($periods as $position => $period) {
            $isBaseline = $position >= count($periods) - 3;
            $volume = $isBaseline ? 1.0 : 1 + $this->noise(0.03);

            $company->monthlyFinancials()->create([
                'period' => CarbonImmutable::createFromFormat('!Y-m', $period)->toDateString(),
                'revenue' => round(self::MonthlyRevenue * $indexByPeriod[$period] / $index * $volume, 2),
                'selling_price_index' => round($indexByPeriod[$period], 3),
            ]);
        }
    }

    /**
     * A cheaper milk supplier for half the volume, and a 12-month fixed electricity price.
     *
     * @param  array<string, CostLine>  $costLines
     */
    private function seedOffers(Company $company, array $costLines): void
    {
        $milkPrice = self::Lines[1]['unit_price'];
        $electricityPrice = self::Lines[6]['unit_price'];

        $company->supplierOffers()->create([
            'supplier_id' => $company->suppliers()->create(['name' => 'Qumështorja e Re'])->id,
            'cost_line_id' => $costLines['Milk']->id,
            'kind' => OfferKind::AlternativeSupplier,
            'unit_price' => round($milkPrice * 0.91, 4),
            'max_share' => 0.5,
            'quoted_on' => '2026-09-20',
        ]);

        $company->supplierOffers()->create([
            'supplier_id' => $company->suppliers()->create(['name' => 'Energji Fikse'])->id,
            'cost_line_id' => $costLines['Electricity']->id,
            'kind' => OfferKind::FixedPrice,
            'unit_price' => round($electricityPrice * 1.04, 4),
            'duration_months' => 12,
            'quoted_on' => '2026-09-25',
        ]);
    }

    private function noise(float $amplitude): float
    {
        return (mt_rand() / mt_getrandmax() * 2 - 1) * $amplitude;
    }
}
