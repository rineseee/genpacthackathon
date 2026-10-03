<?php

namespace App\Services\Margin\Alerts;

use App\Enums\AlertMetric;
use App\Models\AlertRule;
use App\Models\Company;
use App\Models\SimulationRun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Checks a company's owner-set alert rules and hands every crossed one to the n8n
 * workflow that emails the owner. The engine produces every number in the payload;
 * n8n only formats and delivers it.
 */
class AlertDispatcher
{
    /**
     * Check every rule of a company against the observed metrics and send the crossed ones.
     *
     * @param  array<string, float>  $observedMetrics  Observed value per AlertMetric value.
     * @return Collection<int, AlertRule> The rules that were sent.
     */
    public function check(Company $company, array $observedMetrics): Collection
    {
        return $company->alertRules()
            ->get()
            ->filter(fn (AlertRule $rule): bool => $this->isCrossed($rule, $observedMetrics) && ! $this->isCoolingDown($rule))
            ->filter(fn (AlertRule $rule): bool => $this->send($company, $rule, $observedMetrics[$rule->metric->value]))
            ->values();
    }

    /**
     * @param  array<string, float>  $observedMetrics
     */
    private function isCrossed(AlertRule $rule, array $observedMetrics): bool
    {
        $observed = $observedMetrics[$rule->metric->value] ?? null;

        return $observed !== null && $observed >= $rule->threshold;
    }

    private function isCoolingDown(AlertRule $rule): bool
    {
        return $rule->last_triggered_at !== null
            && $rule->last_triggered_at->gt(now()->subDays(config('margin.alerts.cooldown_days')));
    }

    private function send(Company $company, AlertRule $rule, float $observed): bool
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['X-Margin-Token' => (string) config('margin.alerts.n8n_token')])
                ->post(config('margin.alerts.n8n_webhook_url'), $this->payload($company, $rule, $observed));
        } catch (Throwable $exception) {
            Log::warning('Margin alert could not reach n8n.', ['alert_rule_id' => $rule->id, 'error' => $exception->getMessage()]);

            return false;
        }

        if ($response->failed()) {
            Log::warning('n8n rejected a margin alert.', ['alert_rule_id' => $rule->id, 'status' => $response->status()]);

            return false;
        }

        $rule->update(['last_triggered_at' => now()]);

        return true;
    }

    /**
     * @return array{alert_rule_id: int, metric: string, metric_name: string, unit: string, observed: float, threshold: float, label: string, recipient_email: string, triggered_at: string, company: array{id: int, name: string, industry: ?string}, simulation: ?array{id: int, scenario: string, paths: int, seed: int}, dashboard_url: ?string}
     */
    private function payload(Company $company, AlertRule $rule, float $observed): array
    {
        $run = $this->latestRun($company);

        return [
            'alert_rule_id' => $rule->id,
            'metric' => $rule->metric->value,
            'metric_name' => $this->metricName($rule->metric),
            'unit' => $rule->metric === AlertMetric::StressProbability ? 'fraction' : 'EUR',
            'observed' => $observed,
            'threshold' => $rule->threshold,
            'label' => 'forecast',
            'recipient_email' => $rule->recipient_email,
            'triggered_at' => now()->toIso8601String(),
            'company' => ['id' => $company->id, 'name' => $company->name, 'industry' => $company->industry],
            'simulation' => $run === null ? null : ['id' => $run->id, 'scenario' => $run->scenario, 'paths' => $run->paths, 'seed' => $run->seed],
            'dashboard_url' => config('margin.alerts.dashboard_url'),
        ];
    }

    private function metricName(AlertMetric $metric): string
    {
        return match ($metric) {
            AlertMetric::StressProbability => 'Probability of a cash stress event (next 6 months)',
            AlertMetric::MarginAtRisk => 'Margin at risk next quarter',
            AlertMetric::SupplierOvercharge => 'Largest supplier overcharge vs market (per month)',
        };
    }

    private function latestRun(Company $company): ?SimulationRun
    {
        return $company->simulationRuns()->where('scenario', 'baseline')->latest('id')->first();
    }
}
