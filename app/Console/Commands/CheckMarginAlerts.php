<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Margin\Alerts\AlertDispatcher;
use App\Services\Margin\MarginRadar;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('margin:check-alerts {--company= : Only check this company id}')]
#[Description('Send every crossed owner alert to the n8n email workflow')]
class CheckMarginAlerts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(MarginRadar $radar, AlertDispatcher $dispatcher): int
    {
        $companies = Company::query()
            ->when($this->option('company'), fn ($query, string $companyId) => $query->whereKey($companyId))
            ->has('alertRules')
            ->get();

        $sentCount = 0;

        foreach ($companies as $company) {
            try {
                $observedMetrics = $radar->analyse($company)['alert_metrics'];
            } catch (Throwable $exception) {
                $this->warn("Skipped {$company->name}: the analysis failed ({$exception->getMessage()})");

                continue;
            }

            $sent = $dispatcher->check($company, $observedMetrics);

            foreach ($sent as $rule) {
                $this->line("Sent {$rule->metric->value} alert for {$company->name} to {$rule->recipient_email}");
            }

            $sentCount += $sent->count();
        }

        $this->info("{$sentCount} alert(s) sent.");

        return self::SUCCESS;
    }
}
