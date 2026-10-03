<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatementLabel;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Margin\Classification\ExpenseClassifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Preview how messy expense descriptions would be classified. Nothing is saved.
 */
class ExpenseClassificationController extends Controller
{
    public function store(Request $request, Company $company, ExpenseClassifier $classifier): JsonResponse
    {
        $validated = $request->validate([
            'descriptions' => ['required', 'array', 'min:1', 'max:200'],
            'descriptions.*' => ['required', 'string', 'max:255'],
        ]);

        $results = array_map(function (string $description) use ($classifier): array {
            $classification = $classifier->classify($description);

            return $classification === null
                ? ['description' => $description, 'classification' => null, 'label' => StatementLabel::NeedsValidation->value]
                : ['description' => $description, 'classification' => $classification->toArray(), 'label' => StatementLabel::AiSuggestion->value];
        }, $validated['descriptions']);

        return response()->json(['data' => $results]);
    }
}
