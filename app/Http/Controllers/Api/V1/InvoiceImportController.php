<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Margin\InvoiceImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Upload a CSV of supplier invoice lines.
 */
class InvoiceImportController extends Controller
{
    public function store(Request $request, Company $company, InvoiceImporter $importer): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $summary = $importer->import($company, $request->file('file')->getRealPath());

        return response()->json(['data' => $summary], $summary['imported'] > 0 ? 201 : 422);
    }
}
