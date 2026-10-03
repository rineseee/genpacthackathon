<?php

namespace App\Console\Commands;

use App\Services\Margin\Ask\AskPriceImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('margin:import-ask {--snapshot : Also write the snapshot the seeder uses offline}')]
#[Description('Import price drivers (HICP groups and wages) from the Kosovo Agency of Statistics')]
class ImportAskData extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AskPriceImporter $importer): int
    {
        $payload = $importer->fetch();
        $stored = $importer->store($payload);

        $this->table(['Driver', 'Latest month', 'Change over 12 months'], array_map(function (string $code, array $driver): array {
            $observations = $driver['observations'];
            $latest = array_key_last($observations);
            $yearAgo = $observations[date('Y-m', strtotime($latest.'-01 -12 months'))] ?? null;

            return [$code, $latest, $yearAgo ? round(($observations[$latest] / $yearAgo - 1) * 100, 1).'%' : '-'];
        }, array_keys($payload['drivers']), $payload['drivers']));

        if ($this->option('snapshot')) {
            $path = (string) config('margin.ask.snapshot_path');
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
            $this->components->info("Snapshot written to {$path}.");
        }

        $this->components->info("Stored {$stored} observations from {$payload['source']}.");

        return self::SUCCESS;
    }
}
