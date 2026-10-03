<?php

namespace App\Services\Margin\Classification;

/**
 * Links a raw expense line ("Vaj Luledielli 5L") to a cost category and price driver.
 * Returns null when it cannot tell, so the line is sent to the owner for validation.
 */
interface ExpenseClassifier
{
    public function classify(string $description): ?ExpenseClassification;
}
