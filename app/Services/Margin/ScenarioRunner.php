<?php

namespace App\Services\Margin;

use App\Enums\SimulationScenario;
use App\Enums\StatementLabel;
use App\Models\Company;
use App\Models\SimulationRun;
use App\Services\Margin\Simulation\MonteCarloSimulator;
use App\Services\Margin\Simulation\SimulationActionFactory;
use App\Services\Margin\Simulation\SimulationInputBuilder;

/**
 * Answers what-if questions ("if fuel goes up 20% and I raise prices 3%, what happens to my profit?")
 * by simulating the question next to the plain baseline on the same seed, and storing the run.
 */
final class ScenarioRunner
{
    public function __construct(
        private CompanyProfileBuilder $profileBuilder,
        private SimulationInputBuilder $inputBuilder,
        private SimulationActionFactory $actionFactory,
        private MonteCarloSimulator $simulator,
    ) {}

    /**
     * @param  array{scenario?: string, shocks?: array<string, float|int>, actions?: list<array<string, mixed>>, paths?: int, seed?: int}  $request
     */
    public function run(Company $company, array $request): SimulationRun
    {
        $profile = $this->profileBuilder->build($company);
        $scenario = SimulationScenario::from($request['scenario'] ?? SimulationScenario::Baseline->value);
        $shocks = array_map(fn (float|int $percent): float => $percent / 100, $request['shocks'] ?? []);
        $actions = [];

        foreach ($request['actions'] ?? [] as $index => $definition) {
            $actions[] = $this->actionFactory->make($definition, $profile, $index);
        }

        $paths = $request['paths'] ?? null;
        $seed = $request['seed'] ?? null;
        $referenceInput = $this->inputBuilder->build($profile, paths: $paths, seed: $seed);
        $input = $this->inputBuilder->build($profile, $scenario, $shocks, $actions, $paths, $seed);

        $reference = $this->simulator->run($referenceInput);
        $result = $this->simulator->run($input);

        return $company->simulationRuns()->create([
            'scenario' => $this->label($scenario, $shocks, $actions),
            'seed' => $input->seed,
            'paths' => $input->paths,
            'horizon_months' => $input->months,
            'input' => $input->toArray(),
            'result' => $result->toArray() + ['presentation' => json_decode(json_encode([
                'outcome' => $result->outcome(),
                'baseline_outcome' => $reference->outcome(),
                'profit_change_vs_baseline' => LabelledValue::euros($result->expectedTotalProfit - $reference->expectedTotalProfit, StatementLabel::Forecast),
                'stress_probability_change_vs_baseline' => LabelledValue::percent($result->stressProbability - $reference->stressProbability, StatementLabel::Forecast),
                'scenario_label' => $scenario === SimulationScenario::Replay2022 ? StatementLabel::Assumption->value : StatementLabel::Forecast->value,
            ]), true)],
        ]);
    }

    /**
     * "baseline" is only used for an untouched baseline, so alerting can rely on it.
     *
     * @param  array<string, float>  $shocks
     * @param  list<mixed>  $actions
     */
    private function label(SimulationScenario $scenario, array $shocks, array $actions): string
    {
        return $scenario->value.($shocks === [] ? '' : '+what_if').($actions === [] ? '' : '+actions');
    }
}
