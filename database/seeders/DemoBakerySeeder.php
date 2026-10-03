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
 * Demo company: a fictional 3-location bakery with 18 months of invoices and sales.
 *
 * Invoice prices are generated from the price drivers with a known pass-through and lag, plus
 * noise, so the engine has a real link to learn. The flour supplier quietly adds a markup over
 * the last months, which the supplier watch should catch. Requires PriceDriverSeeder.
 */
class DemoBakerySeeder extends Seeder
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
        ['name' => 'Flour', 'description' => 'Miell T-500 thes 50kg', 'category' => CostCategory::Ingredients, 'unit' => 'kg', 'driver' => 'commodity.wheat', 'pass_through' => 0.7, 'lag' => 2, 'spend' => 9000, 'unit_price' => 0.50, 'supplier' => 'Mulliri Veri', 'invoices_per_month' => 4],
        ['name' => 'Cooking oil', 'description' => 'Vaj Luledielli 5L', 'category' => CostCategory::Ingredients, 'unit' => 'l', 'driver' => 'commodity.sunflower_oil', 'pass_through' => 0.8, 'lag' => 1, 'spend' => 2500, 'unit_price' => 2.50, 'supplier' => 'Distributori Dardana', 'storable' => true, 'storage_cost_rate' => 0.01, 'invoices_per_month' => 2],
        ['name' => 'Sugar', 'description' => 'Sheqer kristal 50kg', 'category' => CostCategory::Ingredients, 'unit' => 'kg', 'driver' => 'commodity.sugar', 'pass_through' => 0.7, 'lag' => 1, 'spend' => 1500, 'unit_price' => 0.75, 'supplier' => 'Distributori Dardana', 'storable' => true, 'storage_cost_rate' => 0.005, 'invoices_per_month' => 2],
        ['name' => 'Dairy', 'description' => 'Gjalpë 82% dhe qumësht', 'category' => CostCategory::Ingredients, 'unit' => 'kg', 'driver' => 'commodity.dairy', 'pass_through' => 0.8, 'lag' => 1, 'spend' => 4500, 'unit_price' => 6.00, 'supplier' => 'Bulmeti Lokal', 'invoices_per_month' => 4],
        ['name' => 'Eggs', 'description' => 'Vezë L 30 copë', 'category' => CostCategory::Ingredients, 'unit' => 'tray', 'driver' => 'cpi.food', 'pass_through' => 1.0, 'lag' => 0, 'spend' => 1800, 'unit_price' => 4.50, 'supplier' => 'Ferma Kodra', 'invoices_per_month' => 4],
        ['name' => 'Packaging', 'description' => 'Kuti dhe qese letre', 'category' => CostCategory::Packaging, 'unit' => 'pack', 'driver' => 'cpi.headline', 'pass_through' => 1.0, 'lag' => 1, 'spend' => 1700, 'unit_price' => 12.00, 'supplier' => 'Distributori Dardana', 'storable' => true, 'storage_cost_rate' => 0.005, 'invoices_per_month' => 1, 'status' => MappingStatus::Suggested, 'confidence' => 0.6],
        ['name' => 'Electricity', 'description' => 'Rryma - fatura mujore 3 lokale', 'category' => CostCategory::Energy, 'unit' => 'kWh', 'driver' => 'energy.electricity', 'pass_through' => 1.0, 'lag' => 0, 'spend' => 5500, 'unit_price' => 0.11, 'supplier' => 'Energjia Demo', 'invoices_per_month' => 1],
        ['name' => 'Fuel', 'description' => 'Naftë D2 për furgonët', 'category' => CostCategory::Transport, 'unit' => 'l', 'driver' => 'fuel.diesel', 'pass_through' => 0.9, 'lag' => 0, 'spend' => 1600, 'unit_price' => 1.45, 'supplier' => 'Pika e Karburantit', 'invoices_per_month' => 3],
        ['name' => 'Wages', 'description' => 'Pagat e stafit', 'category' => CostCategory::Wages, 'unit' => 'month', 'driver' => 'wages.kosovo', 'pass_through' => 1.0, 'lag' => 0, 'spend' => 19000, 'unit_price' => 19000, 'supplier' => null, 'invoices_per_month' => 1],
        ['name' => 'Rent', 'description' => 'Qiraja e 3 lokaleve', 'category' => CostCategory::Rent, 'unit' => 'month', 'driver' => null, 'pass_through' => 0.0, 'lag' => 0, 'spend' => 6000, 'unit_price' => 6000, 'supplier' => 'Pronari i Lokaleve', 'invoices_per_month' => 1],
        ['name' => 'Other ingredients', 'description' => 'Maja, kripë dhe të tjera', 'category' => CostCategory::Ingredients, 'unit' => 'pack', 'driver' => 'cpi.food', 'pass_through' => 1.0, 'lag' => 0, 'spend' => 900, 'unit_price' => 9.00, 'supplier' => 'Distributori Dardana', 'invoices_per_month' => 1],
    ];

    /**
     * Extra markup the flour supplier adds on top of the market, by month.
     *
     * @var array<string, float>
     */
    private const array FlourMarkup = ['2026-06' => 1.04, '2026-07' => 1.08, '2026-08' => 1.11, '2026-09' => 1.115];

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

        Company::query()->where('name', 'Furra Demo')->delete();

        $company = Company::create([
            'name' => 'Furra Demo',
            'industry' => 'bakery',
            'locations' => 3,
            'currency' => 'EUR',
            'cash_balance' => 30000,
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
            'metric' => 'stress_probability',
            'threshold' => 0.4,
            'recipient_email' => 'owner@furra-demo.test',
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
        $latestMarkup = $definition['name'] === 'Flour' ? self::FlourMarkup[self::AsOf] : 1.0;
        $rows = [];

        foreach ($periods as $index => $period) {
            $driverValue = $anchor === null ? null : MonthlySeries::valueAtOrBefore($driverSeries, MonthlySeries::shift($period, -$lag));
            $marketFactor = $driverValue === null ? 1.0 : 1 + $definition['pass_through'] * ($driverValue / $anchor - 1);
            $markup = $definition['name'] === 'Flour' ? (self::FlourMarkup[$period] ?? 1.0) / $latestMarkup : 1.0;
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
     * A cheaper flour mill for half the volume, and a 12-month fixed electricity price.
     *
     * @param  array<string, CostLine>  $costLines
     */
    private function seedOffers(Company $company, array $costLines): void
    {
        $flourPrice = self::Lines[0]['unit_price'];
        $electricityPrice = self::Lines[6]['unit_price'];

        $company->supplierOffers()->create([
            'supplier_id' => $company->suppliers()->create(['name' => 'Agro Fusha'])->id,
            'cost_line_id' => $costLines['Flour']->id,
            'kind' => OfferKind::AlternativeSupplier,
            'unit_price' => round($flourPrice * 0.91, 4),
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
