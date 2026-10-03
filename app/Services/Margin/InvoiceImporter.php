<?php

namespace App\Services\Margin;

use App\Enums\MappingStatus;
use App\Models\Company;
use App\Models\CostLine;
use App\Models\PriceDriver;
use App\Services\Margin\Classification\ExpenseClassifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use SplFileObject;
use Throwable;

/**
 * Imports supplier invoice lines from a CSV export (date, supplier, description, quantity, unit, unit_price),
 * classifying each description and linking it to a cost line. New links are suggestions until confirmed.
 */
final class InvoiceImporter
{
    public const array Columns = ['date', 'supplier', 'description', 'quantity', 'unit', 'unit_price'];

    public function __construct(private ExpenseClassifier $classifier) {}

    /**
     * @return array{imported: int, classified: int, needs_validation: list<array{row: int, description: string}>, errors: list<array{row: int, message: string}>, new_cost_lines: list<string>}
     */
    public function import(Company $company, string $path): array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);
        $header = null;
        $summary = ['imported' => 0, 'classified' => 0, 'needs_validation' => [], 'errors' => [], 'new_cost_lines' => []];
        $drivers = PriceDriver::query()->pluck('id', 'code');
        $costLines = $company->costLines()->get()->keyBy('name');
        $suppliers = $company->suppliers()->get()->keyBy('name');
        $rows = [];

        foreach ($file as $index => $values) {
            if ($values === [null] || $values === false) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn (?string $value): string => mb_strtolower(trim((string) $value)), $values);

                if (array_diff(self::Columns, $header) !== []) {
                    $summary['errors'][] = ['row' => 1, 'message' => 'Expected columns: '.implode(', ', self::Columns).'.'];

                    return $summary;
                }

                continue;
            }

            $row = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), null));
            $rowNumber = $index + 1;
            $parsed = $this->parse($row);

            if (is_string($parsed)) {
                $summary['errors'][] = ['row' => $rowNumber, 'message' => $parsed];

                continue;
            }

            $classification = $this->classifier->classify($parsed['description']);
            $costLineId = null;

            if ($classification !== null) {
                $costLine = $costLines->get($classification->lineName);

                if ($costLine === null) {
                    $costLine = $company->costLines()->create([
                        'price_driver_id' => $classification->driverCode === null ? null : $drivers->get($classification->driverCode),
                        'name' => $classification->lineName,
                        'category' => $classification->category,
                        'unit' => $parsed['unit'],
                        'scales_with_volume' => $classification->category->scalesWithVolume(),
                        'mapping_status' => MappingStatus::Suggested,
                        'mapping_confidence' => $classification->confidence,
                    ]);
                    $costLines->put($costLine->name, $costLine);
                    $summary['new_cost_lines'][] = $costLine->name;
                }

                $costLineId = $costLine->id;
                $summary['classified']++;
            } else {
                $summary['needs_validation'][] = ['row' => $rowNumber, 'description' => $parsed['description']];
            }

            $supplierId = null;

            if ($parsed['supplier'] !== '') {
                $supplier = $suppliers->get($parsed['supplier']) ?? $company->suppliers()->create(['name' => $parsed['supplier']]);
                $suppliers->put($supplier->name, $supplier);
                $supplierId = $supplier->id;
            }

            $rows[] = [
                'company_id' => $company->id,
                'supplier_id' => $supplierId,
                'cost_line_id' => $costLineId,
                'invoiced_on' => $parsed['date'],
                'description' => $parsed['description'],
                'quantity' => $parsed['quantity'],
                'unit' => $parsed['unit'],
                'unit_price' => $parsed['unit_price'],
                'total' => round($parsed['quantity'] * $parsed['unit_price'], 2),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($company, $rows): void {
            foreach (array_chunk($rows, 500) as $chunk) {
                $company->invoiceLines()->insert($chunk);
            }
        });

        $summary['imported'] = count($rows);

        return $summary;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array{date: string, supplier: string, description: string, quantity: float, unit: string|null, unit_price: float}|string
     */
    private function parse(array $row): array|string
    {
        try {
            $date = CarbonImmutable::parse(trim((string) $row['date']))->toDateString();
        } catch (Throwable) {
            return 'Unreadable date.';
        }

        $quantity = $this->number($row['quantity']);
        $unitPrice = $this->number($row['unit_price']);
        $description = trim((string) $row['description']);

        if ($description === '' || $quantity === null || $quantity <= 0 || $unitPrice === null || $unitPrice < 0) {
            return 'Description, a positive quantity and a unit price are required.';
        }

        return [
            'date' => $date,
            'supplier' => mb_substr(trim((string) $row['supplier']), 0, 255),
            'description' => mb_substr($description, 0, 255),
            'quantity' => $quantity,
            'unit' => trim((string) $row['unit']) ?: null,
            'unit_price' => $unitPrice,
        ];
    }

    /**
     * Accepts "1234.5", "1,234.5" and the local "1.234,5" formats.
     */
    private function number(?string $value): ?float
    {
        $value = str_replace([' ', '€'], '', trim((string) $value));

        if (preg_match('/^\d{1,3}(\.\d{3})*,\d+$/', $value) === 1 || preg_match('/^\d+,\d+$/', $value) === 1) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
