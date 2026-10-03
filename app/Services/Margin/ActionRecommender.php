<?php

namespace App\Services\Margin;

use App\Enums\OfferKind;
use App\Enums\StatementLabel;
use App\Services\Margin\Simulation\Actions\BuyAhead;
use App\Services\Margin\Simulation\Actions\FixedPriceContract;
use App\Services\Margin\Simulation\Actions\RaisePrices;
use App\Services\Margin\Simulation\Actions\SimulationAction;
use App\Services\Margin\Simulation\Actions\SwitchSupplier;
use App\Services\Margin\Simulation\MonteCarloSimulator;
use App\Services\Margin\Simulation\SimulationInput;
use App\Services\Margin\Simulation\SimulationResult;

/**
 * Builds candidate responses from the company's data, simulates each against doing nothing
 * on identical futures, and assembles the best non-conflicting ones into a plan.
 */
final class ActionRecommender
{
    private const int BuyAheadMonths = 3;

    private const int MaxPlanActions = 4;

    /** Expected gain over the horizon (EUR) below which an action is not worth the owner's attention in a plan. */
    private const float MinPlanGain = 100.0;

    public function __construct(private MonteCarloSimulator $simulator) {}

    /**
     * @return array{actions: list<RecommendedAction>, plan: list<RecommendedAction>, plan_result: SimulationResult}
     */
    public function recommend(CompanyProfile $profile, SimulationInput $baselineInput, SimulationResult $baseline): array
    {
        $recommended = [];

        foreach ($this->costCandidates($profile) as [$action, $title, $metrics]) {
            $candidate = $this->evaluate($action, $title, $metrics, $baselineInput, $baseline);

            if ($candidate->profitGain > 0) {
                $recommended[] = $candidate;
            }
        }

        $byValue = fn (RecommendedAction $a, RecommendedAction $b): int => $b->profitGain <=> $a->profitGain;
        usort($recommended, $byValue);

        [$plan, $planResult] = $this->plan($recommended, [], $baselineInput, $baseline, self::MaxPlanActions - 1);

        // Price only for the gap the cost-side actions cannot close.
        $pricing = $this->pricingCandidate($profile, $planResult);

        if ($pricing !== null) {
            $candidate = $this->evaluate($pricing[0], $pricing[1], $pricing[2], $baselineInput, $baseline);

            if ($candidate->profitGain > 0) {
                $recommended[] = $candidate;
                usort($recommended, $byValue);
                [$plan, $planResult] = $this->plan([$candidate], $plan, $baselineInput, $planResult, self::MaxPlanActions);
            }
        }

        usort($plan, $byValue);

        return ['actions' => $recommended, 'plan' => $plan, 'plan_result' => $planResult];
    }

    /**
     * Simulate any action against doing nothing.
     *
     * @param  array<string, LabelledValue>  $metrics
     */
    public function evaluate(SimulationAction $action, string $title, array $metrics, SimulationInput $baselineInput, SimulationResult $baseline): RecommendedAction
    {
        $result = $this->simulator->run($baselineInput->withActions([$action]));

        return $this->compare($action, $title, $metrics, $result, $baseline);
    }

    /**
     * @return list<array{0: SimulationAction, 1: string, 2: array<string, LabelledValue>}>
     */
    private function costCandidates(CompanyProfile $profile): array
    {
        $candidates = [];
        $offers = $profile->company->supplierOffers()->with('supplier')->orderBy('id')->get();

        foreach ($offers as $offer) {
            $line = $profile->line($offer->cost_line_id);

            if ($line === null || $line->latestUnitPrice <= 0) {
                continue;
            }

            $ratio = $offer->unit_price / $line->latestUnitPrice;
            $lineName = mb_strtolower($line->costLine->name);

            if ($offer->kind === OfferKind::AlternativeSupplier && $ratio < 1) {
                $sharePercent = round($offer->max_share * 100);
                $cheaperPercent = round((1 - $ratio) * 100, 1);
                $candidates[] = [
                    new SwitchSupplier($line->costLine->id, $offer->max_share, $ratio, $offer->id, $offer->supplier->name),
                    "Buy {$sharePercent}% of your {$lineName} from {$offer->supplier->name} ({$cheaperPercent}% cheaper today)",
                    [],
                ];
            }

            if ($offer->kind === OfferKind::FixedPrice) {
                $months = $offer->duration_months ?? (int) config('margin.simulation.horizon_months');
                $premiumPercent = round(($ratio - 1) * 100, 1);
                $candidates[] = [
                    new FixedPriceContract($line->costLine->id, $ratio, $months, $offer->id, $offer->supplier->name),
                    "Lock {$lineName} at a fixed price with {$offer->supplier->name} for {$months} months ({$premiumPercent}% vs today)",
                    [],
                ];
            }
        }

        foreach ($profile->lines as $line) {
            if ($line->costLine->storable && $line->driverCode() !== null) {
                $months = self::BuyAheadMonths;
                $lineName = mb_strtolower($line->costLine->name);
                $candidates[] = [
                    new BuyAhead($line->costLine->id, $months, $line->costLine->storage_cost_rate),
                    "Buy {$months} months of {$lineName} now",
                    ['upfront_cash' => LabelledValue::euros($line->currentMonthlySpend * ($months - 1), StatementLabel::Forecast)],
                ];
            }
        }

        return $candidates;
    }

    /**
     * The price rise that would restore today's profit at the end of the horizon, split into two steps,
     * with the honest break-even: how far sales may fall before the rise is worse than doing nothing.
     *
     * @return array{0: SimulationAction, 1: string, 2: array<string, LabelledValue>}|null
     */
    private function pricingCandidate(CompanyProfile $profile, SimulationResult $afterCostActions): ?array
    {
        $gap = $profile->currentMonthlyProfit() - $afterCostActions->finalMonth()['mean'];

        if ($gap <= 0 || $profile->currentMonthlyRevenue <= 0) {
            return null;
        }

        $increase = min(0.10, max(0.01, ceil($gap / $profile->currentMonthlyRevenue * 200) / 200));
        $step = $increase / 2;
        $action = new RaisePrices([1 => $step, 3 => $step]);
        $contributionMargin = 1 - $profile->variableCostRatio();
        $breakEvenVolumeLoss = $action->totalIncrease() / ($contributionMargin + $action->totalIncrease());
        $increasePercent = round($increase * 100, 1);
        $stepPercent = round($step * 100, 2);

        return [
            $action,
            "Raise selling prices {$increasePercent}% in two steps of {$stepPercent}% (month 1 and month 3)",
            [
                'break_even_volume_loss' => LabelledValue::percent($breakEvenVolumeLoss, StatementLabel::Forecast),
                'assumed_price_elasticity' => new LabelledValue($profile->company->price_elasticity, 'ratio', StatementLabel::Assumption),
            ],
        ];
    }

    /**
     * Greedy plan: add each recommended action in order of value if it does not touch an already
     * planned cost line and it improves the combined result.
     *
     * @param  list<RecommendedAction>  $recommended
     * @param  list<RecommendedAction>  $plan  Actions already in the plan.
     * @param  SimulationResult  $planResult  Result of the plan so far.
     * @param  int  $maxActions  Maximum size of the plan.
     * @return array{0: list<RecommendedAction>, 1: SimulationResult}
     */
    private function plan(array $recommended, array $plan, SimulationInput $baselineInput, SimulationResult $planResult, int $maxActions): array
    {
        $planActions = array_map(fn (RecommendedAction $planned): SimulationAction => $planned->action, $plan);
        $usedLines = [];

        foreach ($planActions as $action) {
            if ($action->costLineId() !== null) {
                $usedLines[$action->costLineId()] = true;
            }
        }

        foreach ($recommended as $candidate) {
            if (count($plan) >= $maxActions) {
                break;
            }

            $lineId = $candidate->action->costLineId();

            if ($candidate->profitGain < self::MinPlanGain || ($lineId !== null && isset($usedLines[$lineId]))) {
                continue;
            }

            $trial = $this->simulator->run($baselineInput->withActions([...$planActions, $candidate->action]));

            if ($trial->expectedTotalProfit - $planResult->expectedTotalProfit < self::MinPlanGain) {
                continue;
            }

            $plan[] = $candidate;
            $planActions[] = $candidate->action;
            $planResult = $trial;

            if ($lineId !== null) {
                $usedLines[$lineId] = true;
            }
        }

        return [$plan, $planResult];
    }

    /**
     * @param  array<string, LabelledValue>  $metrics
     */
    private function compare(SimulationAction $action, string $title, array $metrics, SimulationResult $result, SimulationResult $baseline): RecommendedAction
    {
        return new RecommendedAction(
            action: $action,
            title: $title,
            result: $result,
            profitGain: $result->expectedTotalProfit - $baseline->expectedTotalProfit,
            quarterProfitGain: $result->firstQuarterProfit['mean'] - $baseline->firstQuarterProfit['mean'],
            finalMonthProfitGain: $result->finalMonth()['mean'] - $baseline->finalMonth()['mean'],
            stressProbabilityChange: $result->stressProbability - $baseline->stressProbability,
            metrics: $metrics,
        );
    }
}
