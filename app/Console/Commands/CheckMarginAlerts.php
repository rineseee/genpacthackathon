<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Margin\Alerts\AlertDispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('margin:check-alerts {--company= : Only check this company id}')]
#[Description('Send every crossed owner alert to the n8n email workflow')]
class CheckMarginAlerts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AlertDispatcher $dispatcher): int
    {
        $companies = Company::query()
            ->when($this->option('company'), fn ($query, string $companyId) => $query->whereKey($companyId))
            ->has('alertRules')
            ->get();

        $sentCount = 0;

        foreach ($companies as $company) {
            $sent = $dispatcher->check($company, $dispatcher->observedMetricsFromLatestRun($company));

            foreach ($sent as $rule) {
                $this->line("Sent {$rule->metric->value} alert for {$company->name} to {$rule->recipient_email}");
            }

            $sentCount += $sent->count();
        }

        $this->info("{$sentCount} alert(s) sent.");

        return self::SUCCESS;
    }
}
