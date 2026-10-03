<?php

namespace App\Services\Margin;

use App\Enums\Confidence;
use App\Enums\StatementLabel;
use App\Models\PriceDriver;
use App\Services\Margin\Simulation\Actions\FixedPriceContract;
use App\Services\Margin\Simulation\MonteCarloSimulator;
use App\Services\Margin\Simulation\SimulatedLine;
use App\Services\Margin\Simulation\SimulationInput;
use RuntimeException;

/**
 * Runs the engine on the few numbers an owner types in (no invoices yet): each cost follows the
 * official ASK series for its kind, with industry-default pass-through, through the same simulation.
 */
final class QuickEstimator
{
    /**
     * Cost fields and the ASK series each one follows; the goods series depends on the industry.
     *
     * @var array<string, array{name: string, driver: string|null, scales: bool}>
     */
    private const array Lines = [
        'goods' => ['name' => 'Goods and raw materials', 'driver' => null, 'scales' => true],
        'salaries' => ['name' => 'Salaries', 'driver' => 'wages.kosovo', 'scales' => false],
        'rent' => ['name' => 'Rent', 'driver' => null, 'scales' => false],
        'utilities' => ['name' => 'Electricity, water, heating', 'driver' => 'cpi.electricity_gas', 'scales' => false],
        'fuel' => ['name' => 'Fuel and delivery', 'driver' => 'cpi.transport_fuel', 'scales' => false],
        'other' => ['name' => 'Other costs', 'driver' => 'cpi.headline', 'scales' => false],
    ];

    /** Which official series the bought-in goods of each industry follow. */
    public const array IndustryGoodsDriver = [
        'cafe' => 'cpi.food',
        'bakery' => 'cpi.bread_cereals',
        'retail' => 'cpi.headline',
        'manufacturing' => 'cpi.headline',
        'services' => 'cpi.headline',
    ];

    public function __construct(
        private CompanyProfileBuilder $profileBuilder,
        private PassThroughEstimator $estimator,
        private MonteCarloSimulator $simulator,
        private GroundingCheck $groundingCheck,
    ) {}

    /**
     * @param  array<string, mixed>  $entered  Validated owner input (amounts in EUR, rises in percent).
     * @return array<string, mixed>
     */
    public function estimate(array $entered): array
    {
        $goodsDriver = self::IndustryGoodsDriver[$entered['industry']];
        $codes = array_values(array_unique(array_filter([...array_column(self::Lines, 'driver'), $goodsDriver, 'cpi.headline'])));
        $drivers = PriceDriver::query()->whereIn('code', $codes)->get()->keyBy('code')->all();

        if (! isset($drivers[$goodsDriver])) {
            throw new RuntimeException('Official price data is not loaded. Run the price driver seeder first.');
        }

        $series = $this->profileBuilder->driverSeries($drivers);
        $outlooks = $this->profileBuilder->outlooks($drivers, $series);
        $asOf = max(array_map(fn (array $values): string => (string) array_key_last($values), array_filter($series)));

        $lines = [];
        $actions = [];
        $id = 0;

        foreach (self::Lines as $field => $definition) {
            $amount = (float) ($entered[$field] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            $id++;
            $driverCode = $field === 'goods' ? $goodsDriver : $definition['driver'];
            $knownRise = ['salaries' => $entered['salary_rise'] ?? null, 'rent' => $entered['rent_rise'] ?? null][$field] ?? null;

            // A rise the owner already knows about replaces the market trend for that line.
            if ($knownRise !== null && (float) $knownRise != 0.0) {
                $driverCode = null;
                $actions[] = new FixedPriceContract($id, 1 + $knownRise / 100, (int) config('margin.simulation.horizon_months'));
            }

            $estimate = $driverCode === null ? null : $this->estimator->industryDefault($drivers[$driverCode]->kind);

            $lines[$field] = new SimulatedLine(
                id: $id,
                name: $definition['name'],
                monthlySpend: $amount,
                driverCode: $driverCode,
                passThrough: $estimate?->passThrough ?? 0.0,
                lagMonths: $estimate?->lagMonths ?? 0,
                pendingDriverChange: $driverCode === null ? 0.0 : $this->profileBuilder->pendingChange($series[$driverCode], $asOf, $estimate->lagMonths),
                scalesWithVolume: $definition['scales'],
            );
        }

        $sales = (float) $entered['sales'];
        $loanInterest = (float) ($entered['loan'] ?? 0) * (float) ($entered['loan_rate'] ?? 0) / 100 / 12;
        $input = new SimulationInput(
            monthlyRevenue: $sales,
            lines: array_values($lines),
            drivers: $outlooks,
            priceElasticity: -0.5,
            startingCash: (float) $entered['cash'],
            minimumCashReserve: (float) ($entered['min_cash'] ?? 0),
            monthlyNonOperatingOutflows: (float) ($entered['drawings'] ?? 0) + $loanInterest,
            months: (int) config('margin.simulation.horizon_months'),
            paths: (int) config('margin.simulation.paths'),
            seed: (int) config('margin.simulation.seed'),
            driverCorrelation: (float) config('margin.simulation.driver_correlation'),
            volumeVolatility: 0.0,
            actions: $actions,
        );

        $result = $this->simulator->run($input, trackLines: true);

        return $this->present($entered, $input, $result, $lines, $drivers, $series, $goodsDriver);
    }

    /**
     * @param  array<string, mixed>  $entered
     * @param  array<string, SimulatedLine>  $lines
     * @param  array<string, PriceDriver>  $drivers
     * @param  array<string, array<string, float>>  $series
     * @return array<string, mixed>
     */
    private function present(array $entered, SimulationInput $input, Simulation\SimulationResult $result, array $lines, array $drivers, array $series, string $goodsDriver): array
    {
        $sales = $input->monthlyRevenue;
        $costs = $input->monthlyCosts();
        $profit = $sales - $costs;
        $final = $result->finalMonth();
        $months = $input->months;

        // Volume is held flat here, so the fall in profit is the rise in costs.
        $extra = ['p50' => $profit - $final['p50'], 'low' => $profit - $final['p90'], 'high' => $profit - $final['p10']];
        $priceRise = $sales > 0 ? $extra['p50'] / $sales : 0.0;
        $runway = $final['p50'] < 0 ? $input->startingCash / abs($final['p50']) : null;

        $lineRows = [];
        foreach ($lines as $field => $line) {
            $lineMonths = $result->lineMultipliers[$line->id];
            $atHorizon = end($lineMonths);
            $driver = $line->driverCode === null ? null : $drivers[$line->driverCode];
            $lineRows[] = [
                'field' => $field,
                'name' => $line->name,
                'monthly_amount' => LabelledValue::euros($line->monthlySpend, StatementLabel::Data),
                'driver' => $driver?->only(['code', 'name', 'source']),
                'official_change_last_12_months' => $driver === null ? null : $this->yearOnYear($series[$driver->code], $driver->source),
                'price_change_in_6_months' => $driver === null ? null : LabelledValue::percent($atHorizon['p50'] - 1, StatementLabel::Forecast, $atHorizon['p10'] - 1, $atHorizon['p90'] - 1, Confidence::Low),
            ];
        }

        $priceRisePercent = round($priceRise * 100, 1);
        $products = array_map(fn (array $product): array => [
            'name' => $product['name'],
            'price_today' => round((float) $product['price'], 2),
            'price_to_hold_profit' => round((float) $product['price'] * (1 + $priceRise), 2),
        ], $entered['products'] ?? []);

        return [
            'as_of' => max(array_map(fn (array $values): string => (string) array_key_last($values), array_filter($series))),
            'industry' => $entered['industry'],
            'horizon_months' => $months,
            'sales' => LabelledValue::euros($sales, StatementLabel::Data),
            'costs_today' => LabelledValue::euros($costs, StatementLabel::Data),
            'profit_today' => LabelledValue::euros($profit, StatementLabel::Data),
            'margin_today' => LabelledValue::percent($sales > 0 ? $profit / $sales : 0, StatementLabel::Data),
            'exposed_costs' => LabelledValue::euros(array_sum(array_map(fn (SimulatedLine $line): float => $line->driverCode === null ? 0.0 : $line->monthlySpend, $lines)), StatementLabel::Data),
            'goods_trend' => $this->yearOnYear($series[$goodsDriver], $drivers[$goodsDriver]->source) + ['driver' => $drivers[$goodsDriver]->only(['code', 'name'])],
            'extra_cost_per_month' => LabelledValue::euros($extra['p50'], StatementLabel::Forecast, $extra['low'], $extra['high'], Confidence::Low),
            'profit_after' => LabelledValue::euros($final['p50'], StatementLabel::Forecast, $final['p10'], $final['p90'], Confidence::Low),
            'price_rise_to_hold_profit' => LabelledValue::percent($priceRise, StatementLabel::Forecast),
            'cash_stress_probability' => LabelledValue::percent($result->stressProbability, StatementLabel::Forecast),
            'runway_months' => $runway === null ? null : round($runway, 1),
            'lines' => $lineRows,
            'products' => $products,
            'summary' => $this->summary($extra['p50'], $final['p50'], $priceRisePercent, $months, $result->stressProbability),
            'assumptions' => [
                'Costs follow official Kosovo Agency of Statistics series with industry-default pass-through (no invoices yet).',
                'Sales volume is held flat; a price rise is assumed to lose half its size in volume (elasticity -0.5) only when you test one.',
            ],
        ];
    }

    /**
     * @param  array<string, float>  $values
     * @return array{value: float, unit: string, label: string, source: string, period: string}|array{}
     */
    private function yearOnYear(array $values, string $source): array
    {
        $period = array_key_last($values);
        $yearAgo = $values[MonthlySeries::shift($period, -12)] ?? null;

        if ($yearAgo === null || $yearAgo <= 0) {
            return [];
        }

        return LabelledValue::percent($values[$period] / $yearAgo - 1, StatementLabel::Data, source: $source)->jsonSerialize() + ['period' => $period];
    }

    /**
     * @return array{text: string, label: string, grounded: bool}
     */
    private function summary(float $extra, float $profitAfter, float $priceRisePercent, int $months, float $stress): array
    {
        $money = fn (float $value): string => ($value < 0 ? '-€' : '€').number_format(abs(round($value)));
        $stressPercent = round($stress * 100, 1);
        $text = "In {$months} months your costs are likely to be about {$money($extra)} a month higher, leaving a monthly profit of about {$money($profitAfter)}. "
            ."Raising prices by about {$priceRisePercent}% would hold today's profit. The chance that your cash falls below the minimum you want to keep is {$stressPercent}%.";

        $facts = [$months, round($extra), round($profitAfter), $priceRisePercent, $stressPercent];

        if (! $this->groundingCheck->isGrounded($text, $facts)) {
            throw new RuntimeException('Estimate summary contains ungrounded numbers.');
        }

        return ['text' => $text, 'label' => StatementLabel::AiSuggestion->value, 'grounded' => true];
    }
}
