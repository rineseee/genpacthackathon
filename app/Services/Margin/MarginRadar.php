<?php

namespace App\Services\Margin;

use App\Enums\Confidence;
use App\Enums\MappingStatus;
use App\Enums\SimulationScenario;
use App\Enums\StatementLabel;
use App\Models\Company;
use App\Models\DriverProjection;
use App\Models\PriceObservation;
use App\Services\Margin\Simulation\MonteCarloSimulator;
use App\Services\Margin\Simulation\SimulationInput;
use App\Services\Margin\Simulation\SimulationInputBuilder;
use App\Services\Margin\Simulation\SimulationResult;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * The full picture for one company: what inflation is doing to its costs and margin, where,
 * what doing nothing looks like, and what each response is worth. Every number comes from
 * the engine; the summary text is checked so it cannot contain any other number.
 */
final class MarginRadar
{
    public function __construct(
        private CompanyProfileBuilder $profileBuilder,
        private CompanyInflationCalculator $inflationCalculator,
        private SimulationInputBuilder $inputBuilder,
        private MonteCarloSimulator $simulator,
        private ActionRecommender $recommender,
        private SupplierWatch $supplierWatch,
        private GroundingCheck $groundingCheck,
    ) {}

    /**
     * Cached until any of the company's data or the public driver data changes.
     *
     * @return array<string, mixed>
     */
    public function analyse(Company $company): array
    {
        return Cache::remember(
            'margin-radar:'.$company->id.':'.$this->fingerprint($company),
            now()->addHour(),
            // Plain arrays only: the cache does not unserialize application objects.
            fn (): array => json_decode(json_encode($this->compute($company), JSON_THROW_ON_ERROR), true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(Company $company): array
    {
        $profile = $this->profileBuilder->build($company);
        $inflation = $this->inflationCalculator->calculate($profile);
        $baselineInput = $this->inputBuilder->build($profile);
        $baseline = $this->simulator->run($baselineInput, trackLines: true);
        $recommendations = $this->recommender->recommend($profile, $baselineInput, $baseline);
        $planResult = $recommendations['plan_result'];
        $flags = $this->supplierWatch->flags($profile);

        $this->store($company, SimulationScenario::Baseline, $baselineInput, $baseline);
        $this->store($company, SimulationScenario::Plan, $baselineInput->withActions(array_map(fn (RecommendedAction $action) => $action->action, $recommendations['plan'])), $planResult);

        $costLines = $this->costLines($profile, $inflation, $baseline);
        $marginAtRisk = $this->marginAtRisk($profile, $baseline);
        $overcharge = array_sum(array_map(fn (SupplierFlag $flag): float => $flag->monthlyOvercharge, $flags));

        return [
            'company' => $company->only(['id', 'name', 'industry', 'locations', 'currency']),
            'as_of' => $profile->asOf,
            'overview' => [
                'revenue_today' => LabelledValue::euros($profile->currentMonthlyRevenue, StatementLabel::Data),
                'costs_today' => LabelledValue::euros($profile->currentMonthlyCosts(), StatementLabel::Data),
                'profit_today' => LabelledValue::euros($profile->currentMonthlyProfit(), StatementLabel::Data),
                'profit_lost_to_inflation_this_month' => LabelledValue::euros($inflation->profitLostThisMonth, StatementLabel::Data),
                'company_cost_inflation' => $this->percentOrNull($inflation->costInflation, StatementLabel::Data),
                'selling_price_growth' => $this->percentOrNull($inflation->sellingPriceGrowth, StatementLabel::Data),
                'headline_cpi' => $inflation->headlineCpi === null ? null : LabelledValue::percent(
                    $inflation->headlineCpi, StatementLabel::Data, source: ($profile->drivers['cpi.headline']->source ?? 'CPI').' '.$inflation->headlineCpiPeriod,
                ),
                'margin_at_risk_next_quarter' => $marginAtRisk,
                'protectable_next_quarter' => LabelledValue::euros($planResult->firstQuarterProfit['mean'] - $baseline->firstQuarterProfit['mean'], StatementLabel::Forecast),
                'do_nothing' => $baseline->outcome(),
                'with_plan' => $planResult->outcome(),
                'top_risks' => array_slice($this->rankedRisks($costLines), 0, 3),
                'top_actions' => array_map(fn (RecommendedAction $action): array => $action->toArray(), array_slice($recommendations['actions'], 0, 3)),
                'supplier_flags' => [
                    'count' => count($flags),
                    'monthly_overcharge' => LabelledValue::euros($overcharge, StatementLabel::Data),
                ],
                'summary' => $this->summary($profile, $inflation, $baseline, $planResult),
            ],
            'cost_lines' => $costLines,
            'recommendations' => [
                'actions' => array_map(fn (RecommendedAction $action): array => $action->toArray(), $recommendations['actions']),
                'plan' => [
                    'actions' => array_map(fn (RecommendedAction $action): array => $action->toArray(), $recommendations['plan']),
                    'outcome' => $planResult->outcome(),
                    'expected_profit_gain' => LabelledValue::euros($planResult->expectedTotalProfit - $baseline->expectedTotalProfit, StatementLabel::Forecast),
                ],
                'do_nothing' => $baseline->outcome(),
            ],
            'supplier_watch' => array_map(fn (SupplierFlag $flag): array => $flag->toArray(), $flags),
            'alert_metrics' => [
                'stress_probability' => $baseline->stressProbability,
                'margin_at_risk' => $marginAtRisk->value,
                'supplier_overcharge' => $flags === [] ? 0.0 : round($flags[0]->monthlyOvercharge, 2),
            ],
        ];
    }

    /**
     * Profit the company is expected to lose over the next quarter compared with earning today's profit.
     */
    private function marginAtRisk(CompanyProfile $profile, SimulationResult $baseline): LabelledValue
    {
        $months = min(3, count($baseline->monthlyProfit));
        $todayForQuarter = $profile->currentMonthlyProfit() * $months;
        $quarter = $baseline->firstQuarterProfit;

        return LabelledValue::euros(
            max(0, $todayForQuarter - $quarter['mean']),
            StatementLabel::Forecast,
            max(0, $todayForQuarter - $quarter['p90']),
            max(0, $todayForQuarter - $quarter['p10']),
            $this->dataConfidence($profile),
        );
    }

    /**
     * Confidence rises with the share of spend whose driver link was learned from the company's own history.
     */
    private function dataConfidence(CompanyProfile $profile): Confidence
    {
        $total = $profile->currentMonthlyCosts();
        $learned = 0.0;

        foreach ($profile->lines as $line) {
            if ($line->estimate?->source === PassThroughEstimate::SourceCompanyHistory) {
                $learned += $line->currentMonthlySpend;
            }
        }

        $share = $total > 0 ? $learned / $total : 0;

        return match (true) {
            $share >= 0.6 => Confidence::High,
            $share >= 0.3 => Confidence::Medium,
            default => Confidence::Low,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function costLines(CompanyProfile $profile, CompanyInflation $inflation, SimulationResult $baseline): array
    {
        $horizon = (int) config('margin.forecast_horizon_months');
        $rows = [];

        foreach ($profile->lines as $line) {
            $costLine = $line->costLine;
            $months = array_slice($baseline->lineMultipliers[$costLine->id] ?? [], 0, $horizon);
            $forecast = null;
            $extraCost = null;

            if ($line->estimate !== null && $months !== []) {
                $atHorizon = end($months);
                $confidence = $this->forecastConfidence($line->estimate->confidence, $atHorizon['p90'] - $atHorizon['p10']);
                $forecast = LabelledValue::percent($atHorizon['p50'] - 1, StatementLabel::Forecast, $atHorizon['p10'] - 1, $atHorizon['p90'] - 1, $confidence);
                $sum = fn (string $key): float => array_sum(array_map(fn (array $month): float => $line->currentMonthlySpend * ($month[$key] - 1), $months));
                $extraCost = LabelledValue::euros($sum('p50'), StatementLabel::Forecast, $sum('p10'), $sum('p90'), $confidence);
            }

            $rows[] = [
                'id' => $costLine->id,
                'name' => $costLine->name,
                'category' => $costLine->category->value,
                'unit' => $costLine->unit,
                'driver' => $costLine->priceDriver?->only(['id', 'code', 'name', 'kind', 'source']),
                'mapping' => [
                    'status' => $costLine->mapping_status->value,
                    'confidence' => $costLine->mapping_confidence,
                    'label' => $costLine->mapping_status === MappingStatus::Confirmed ? StatementLabel::Data->value : StatementLabel::NeedsValidation->value,
                ],
                'monthly_spend' => LabelledValue::euros($line->currentMonthlySpend, StatementLabel::Data),
                'share_of_costs' => LabelledValue::percent($profile->currentMonthlyCosts() > 0 ? $line->currentMonthlySpend / $profile->currentMonthlyCosts() : 0, StatementLabel::Data),
                'inflation_last_12_months' => $this->percentOrNull($inflation->lineInflation[$costLine->id] ?? null, StatementLabel::Data),
                'pass_through' => $line->estimate?->toArray(),
                'price_forecast' => $forecast,
                'extra_cost_next_quarter' => $extraCost,
            ];
        }

        return $rows;
    }

    private function forecastConfidence(Confidence $estimateConfidence, float $width): Confidence
    {
        if ($width <= 0.15) {
            return $estimateConfidence;
        }

        return $estimateConfidence === Confidence::High ? Confidence::Medium : Confidence::Low;
    }

    /**
     * @param  list<array<string, mixed>>  $costLines
     * @return list<array<string, mixed>>
     */
    private function rankedRisks(array $costLines): array
    {
        $risky = array_values(array_filter($costLines, fn (array $line): bool => $line['extra_cost_next_quarter'] !== null && $line['extra_cost_next_quarter']->value > 0));
        usort($risky, fn (array $a, array $b): int => $b['extra_cost_next_quarter']->value <=> $a['extra_cost_next_quarter']->value);

        return array_map(fn (array $line): array => [
            'cost_line_id' => $line['id'],
            'name' => $line['name'],
            'price_forecast' => $line['price_forecast'],
            'extra_cost_next_quarter' => $line['extra_cost_next_quarter'],
        ], $risky);
    }

    /**
     * Plain-language summary built only from engine numbers, then grounding-checked.
     *
     * @return array{text: string, label: string, grounded: bool}
     */
    private function summary(CompanyProfile $profile, CompanyInflation $inflation, SimulationResult $baseline, SimulationResult $plan): array
    {
        $facts = [];
        $money = function (float $value) use (&$facts): string {
            $facts[] = round($value);

            return ($value < 0 ? '-€' : '€').number_format(abs(round($value)));
        };
        $percent = function (float $fraction) use (&$facts): string {
            $facts[] = round($fraction * 100, 1);

            return number_format(round($fraction * 100, 1), 1).'%';
        };
        $count = function (int $value) use (&$facts): string {
            $facts[] = $value;

            return (string) $value;
        };

        $sentences = [];

        if ($inflation->costInflation !== null && $inflation->headlineCpi !== null) {
            $sentence = 'Over the last '.$count(12).' months your costs rose '.$percent($inflation->costInflation).' while official inflation was '.$percent($inflation->headlineCpi);
            $sentence .= $inflation->sellingPriceGrowth !== null ? ' and your own prices rose '.$percent($inflation->sellingPriceGrowth).'.' : '.';
            $sentences[] = $sentence;
            $sentences[] = 'That gap costs you about '.$money($inflation->profitLostThisMonth).' this month.';
        }

        $final = $baseline->finalMonth();
        $sentences[] = 'If nothing changes, monthly profit is likely to move from '.$money($profile->currentMonthlyProfit()).' to about '.$money($final['p50'])
            .' (range '.$money($final['p10']).' to '.$money($final['p90']).') within '.$count(count($baseline->monthlyProfit)).' months, with a '
            .$percent($baseline->stressProbability).' chance of cash falling below your reserve.';

        if ($plan !== $baseline) {
            $sentences[] = 'With the recommended plan, profit at that point is about '.$money($plan->finalMonth()['p50']).' a month and that chance is '.$percent($plan->stressProbability).'.';
        }

        $text = implode(' ', $sentences);
        $ungrounded = $this->groundingCheck->ungroundedNumbers($text, $facts);

        if ($ungrounded !== []) {
            throw new RuntimeException('Summary contains ungrounded numbers: '.implode(', ', $ungrounded));
        }

        return ['text' => $text, 'label' => StatementLabel::AiSuggestion->value, 'grounded' => true];
    }

    private function percentOrNull(?float $fraction, StatementLabel $label): ?LabelledValue
    {
        return $fraction === null ? null : LabelledValue::percent($fraction, $label);
    }

    private function store(Company $company, SimulationScenario $scenario, SimulationInput $input, SimulationResult $result): void
    {
        $company->simulationRuns()->create([
            'scenario' => $scenario->value,
            'seed' => $input->seed,
            'paths' => $input->paths,
            'horizon_months' => $input->months,
            'input' => $input->toArray(),
            'result' => $result->toArray(),
        ]);
    }

    /**
     * Changes whenever anything the analysis reads changes.
     */
    private function fingerprint(Company $company): string
    {
        $parts = [$company->updated_at?->toIso8601String(), config('margin.simulation')];

        foreach ([$company->invoiceLines(), $company->costLines(), $company->supplierOffers(), $company->monthlyFinancials()] as $relation) {
            $parts[] = [$relation->count(), $relation->max('updated_at')];
        }

        $parts[] = [PriceObservation::query()->count(), PriceObservation::query()->max('updated_at')];
        $parts[] = [DriverProjection::query()->count(), DriverProjection::query()->max('updated_at')];

        return md5(json_encode($parts));
    }
}
