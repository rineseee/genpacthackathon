<?php

namespace App\Services\Margin\Classification;

/**
 * Rule-based classifier for Albanian and English invoice text, driven by config('margin.classification_rules').
 */
final class KeywordExpenseClassifier implements ExpenseClassifier
{
    public function classify(string $description): ?ExpenseClassification
    {
        $normalised = $this->normalise($description);

        foreach (config('margin.classification_rules') as $rule) {
            if (preg_match($rule['pattern'], $normalised) === 1) {
                return new ExpenseClassification(
                    description: $description,
                    lineName: $rule['line'],
                    category: $rule['category'],
                    driverCode: $rule['driver'],
                    confidence: $rule['confidence'],
                    method: 'keyword_rules',
                );
            }
        }

        return null;
    }

    private function normalise(string $text): string
    {
        return strtr(mb_strtolower($text), ['ë' => 'e', 'ç' => 'c']);
    }
}
