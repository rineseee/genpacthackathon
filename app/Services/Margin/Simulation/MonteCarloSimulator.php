<?php

namespace App\Services\Margin\Simulation;

use App\Services\Margin\MonthlySeries;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Runs thousands of possible futures through a monthly profit and cash-flow model.
 *
 * The random futures (driver paths and demand noise) depend only on the seed, the driver
 * outlooks and the horizon, never on the actions. Runs that differ only in their actions
 * therefore compare those actions on identical futures (common random numbers), and the
 * futures are generated once and reused across such runs.
 */
final class MonteCarloSimulator
{
    private ?string $futuresKey = null;

    /** @var array{driver_codes: list<string>, levels: list<list<float>>, volume: list<list<float>>}|null */
    private ?array $futures = null;

    /**
     * @param  bool  $trackLines  Also return price percentiles per cost line and month (slower).
     */
    public function run(SimulationInput $input, bool $trackLines = false): SimulationResult
    {
        $futures = $this->futures($input);
        $months = $input->months;
        $driverPosition = array_flip($futures['driver_codes']);

        $sellingPrice = [];
        for ($month = 1; $month <= $months; $month++) {
            $sellingPrice[$month] = 1.0;
            foreach ($input->actions as $action) {
                $sellingPrice[$month] *= $action->sellingPriceMultiplier($month);
            }
        }

        $lines = [];
        foreach ($input->lines as $line) {
            $offset = $line->driverCode !== null && isset($driverPosition[$line->driverCode]) ? $driverPosition[$line->driverCode] * $months : null;
            $pendingFactor = [];
            for ($month = 1; $month <= $months; $month++) {
                $pendingFactor[$month] = $offset !== null && $line->lagMonths > 0
                    ? 1 + $line->passThrough * $line->pendingDriverChange * min($month, $line->lagMonths) / $line->lagMonths
                    : 1.0;
            }

            $lines[] = [
                'line' => $line,
                'offset' => $offset,
                'pending' => $pendingFactor,
                'actions' => array_values(array_filter($input->actions, fn ($action): bool => $action->costLineId() === $line->id)),
            ];
        }

        $quarterMonths = min(3, $months);
        $profitByMonth = array_fill(1, $months, []);
        $lineMultipliers = [];
        $firstQuarterProfits = [];
        $endingCash = [];
        $totalProfit = 0.0;
        $stressedPaths = 0;

        for ($path = 0; $path < $input->paths; $path++) {
            $levels = $futures['levels'][$path];
            $volumeShocks = $futures['volume'][$path];
            $cash = $input->startingCash;
            $stressed = false;
            $pathQuarterProfit = 0.0;

            for ($month = 1; $month <= $months; $month++) {
                $volume = max(0.0, 1 + $input->priceElasticity * ($sellingPrice[$month] - 1))
                    * max(0.0, 1 + $input->volumeVolatility * $volumeShocks[$month - 1]);
                $revenue = $input->monthlyRevenue * $sellingPrice[$month] * $volume;
                $costs = 0.0;
                $extraCash = 0.0;

                foreach ($lines as $entry) {
                    $line = $entry['line'];
                    $market = 1.0;

                    if ($entry['offset'] !== null) {
                        $laggedMonth = $month - $line->lagMonths;
                        $market = $entry['pending'][$month] * ($laggedMonth >= 1 ? 1 + $line->passThrough * ($levels[$entry['offset'] + $laggedMonth - 1] - 1) : 1.0);
                    }

                    $multiplier = $market;
                    foreach ($entry['actions'] as $action) {
                        $multiplier = $action->lineCostMultiplier($line, $month, $multiplier);
                    }

                    $lineCost = $line->monthlySpend * $multiplier * ($line->scalesWithVolume ? $volume : 1.0);
                    $costs += $lineCost;

                    foreach ($entry['actions'] as $action) {
                        $extraCash += $action->extraCashOutflow($line, $month, $lineCost);
                    }

                    if ($trackLines) {
                        $lineMultipliers[$line->id][$month][] = $market;
                    }
                }

                $profit = $revenue - $costs;
                $profitByMonth[$month][] = $profit;
                $totalProfit += $profit;

                if ($month <= $quarterMonths) {
                    $pathQuarterProfit += $profit;
                }

                $cash += $profit - $input->monthlyNonOperatingOutflows - $extraCash;

                if ($cash < $input->minimumCashReserve) {
                    $stressed = true;
                }
            }

            $firstQuarterProfits[] = $pathQuarterProfit;
            $endingCash[] = $cash;

            if ($stressed) {
                $stressedPaths++;
            }
        }

        $monthlyProfit = [];
        foreach ($profitByMonth as $month => $profits) {
            $monthlyProfit[] = ['month' => $month] + $this->summarise($profits);
        }

        $lineSummaries = [];
        foreach ($lineMultipliers as $lineId => $monthValues) {
            foreach ($monthValues as $month => $multipliers) {
                $summary = $this->summarise($multipliers);
                $lineSummaries[$lineId][] = ['month' => $month, 'p10' => $summary['p10'], 'p50' => $summary['p50'], 'p90' => $summary['p90']];
            }
        }

        return new SimulationResult(
            monthlyProfit: $monthlyProfit,
            expectedTotalProfit: $totalProfit / $input->paths,
            firstQuarterProfit: $this->summarise($firstQuarterProfits),
            endingCash: $this->summarise($endingCash),
            stressProbability: $stressedPaths / $input->paths,
            lineMultipliers: $lineSummaries,
            paths: $input->paths,
            seed: $input->seed,
        );
    }

    /**
     * Driver level paths (flat per path: driver position * months + month - 1) and demand shocks,
     * generated once per distinct set of futures.
     *
     * @return array{driver_codes: list<string>, levels: list<list<float>>, volume: list<list<float>>}
     */
    private function futures(SimulationInput $input): array
    {
        $key = md5(serialize([$input->seed, $input->paths, $input->months, $input->drivers, $input->shocks, $input->driverCorrelation]));

        if ($key === $this->futuresKey && $this->futures !== null) {
            return $this->futures;
        }

        $randomizer = new Randomizer(new Mt19937($input->seed));
        $driverCodes = array_keys($input->drivers);
        sort($driverCodes);
        $idiosyncraticWeight = sqrt(1 - $input->driverCorrelation ** 2);
        $normalsPerPath = $input->months * (count($driverCodes) + 2);
        $levels = [];
        $volume = [];

        for ($path = 0; $path < $input->paths; $path++) {
            $normals = $this->normals($randomizer, $normalsPerPath);
            $next = 0;
            $common = [];
            for ($month = 1; $month <= $input->months; $month++) {
                $common[$month] = $normals[$next++];
            }

            $pathLevels = [];
            foreach ($driverCodes as $code) {
                $outlook = $input->drivers[$code];
                $level = 1.0 + ($input->shocks[$code] ?? 0.0);

                for ($month = 1; $month <= $input->months; $month++) {
                    $shock = $input->driverCorrelation * $common[$month] + $idiosyncraticWeight * $normals[$next++];
                    $level *= max(0.05, 1 + $outlook->driftForMonth($month) + $outlook->monthlyVolatility * $shock);
                    $pathLevels[] = $level;
                }
            }

            $levels[] = $pathLevels;
            $volume[] = array_slice($normals, $next, $input->months);
        }

        $this->futuresKey = $key;

        return $this->futures = ['driver_codes' => $driverCodes, 'levels' => $levels, 'volume' => $volume];
    }

    /**
     * @param  list<float>  $values
     * @return array{mean: float, p10: float, p50: float, p90: float}
     */
    private function summarise(array $values): array
    {
        sort($values);

        return [
            'mean' => array_sum($values) / max(1, count($values)),
            'p10' => MonthlySeries::sortedPercentile($values, 0.10),
            'p50' => MonthlySeries::sortedPercentile($values, 0.50),
            'p90' => MonthlySeries::sortedPercentile($values, 0.90),
        ];
    }

    /**
     * Standard normal draws (Box-Muller) from one bulk read of the seeded engine.
     *
     * @return list<float>
     */
    private function normals(Randomizer $randomizer, int $count): array
    {
        $pairs = intdiv($count + 1, 2);
        $integers = unpack('V*', $randomizer->getBytes($pairs * 8));
        $normals = [];

        for ($pair = 0; $pair < $pairs; $pair++) {
            $radius = sqrt(-2 * log(($integers[2 * $pair + 1] + 0.5) / 4294967296));
            $angle = 2 * M_PI * ($integers[2 * $pair + 2] + 0.5) / 4294967296;
            $normals[] = $radius * cos($angle);
            $normals[] = $radius * sin($angle);
        }

        return $normals;
    }
}
