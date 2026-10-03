<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Margin\MarginRadar;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('margin:analyse {company? : Company id; all companies when omitted}')]
#[Description('Run the margin analysis (and warm its cache) and print each company\'s summary')]
class AnalyseCompanies extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(MarginRadar $radar): int
    {
        $companies = Company::query()
            ->when($this->argument('company'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        foreach ($companies as $company) {
            $started = microtime(true);
            $analysis = $radar->analyse($company);

            $this->components->info("{$company->name} (as of {$analysis['as_of']}, ".round(microtime(true) - $started, 1).'s)');
            $this->line($analysis['overview']['summary']['text']);
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
