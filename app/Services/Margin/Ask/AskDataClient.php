<?php

namespace App\Services\Margin\Ask;

use Illuminate\Support\Facades\Http;

/**
 * Minimal client for the ASKdata PxWeb API (askdata.rks-gov.net).
 */
final class AskDataClient
{
    /**
     * @param  string  $table  Table path such as "Prices/Consumer Price Index/Monthly indicators/cpi01.px".
     * @param  array<string, array{filter: string, values: list<string>}>  $selections  Selection keyed by dimension code.
     */
    public function query(string $table, array $selections): JsonStatTable
    {
        $path = implode('/', array_map('rawurlencode', explode('/', $table)));
        $query = [];

        foreach ($selections as $code => $selection) {
            $query[] = ['code' => $code, 'selection' => $selection];
        }

        $response = Http::baseUrl(rtrim((string) config('margin.ask.base_url'), '/').'/')
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 1000)
            ->post($path, ['query' => $query, 'response' => ['format' => 'json-stat2']])
            ->throw();

        return new JsonStatTable($response->json());
    }
}
