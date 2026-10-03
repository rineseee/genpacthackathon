<?php

namespace Tests\Feature\Services\Margin\Classification;

use App\Enums\CostCategory;
use App\Services\Margin\Classification\KeywordExpenseClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KeywordExpenseClassifierTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string|null, 2: CostCategory}>
     */
    public static function invoiceLines(): array
    {
        return [
            'albanian cooking oil' => ['Vaj Luledielli 5L Bimi', 'commodity.sunflower_oil', CostCategory::Ingredients],
            'flour with diacritics' => ['MIELL T-500 thes 50kg', 'commodity.wheat', CostCategory::Ingredients],
            'diesel before oil' => ['Naftë D2 për furgonët', 'fuel.diesel', CostCategory::Transport],
            'electricity bill' => ['KESCO fatura e rrymës', 'energy.electricity', CostCategory::Energy],
            'rent has no driver' => ['Qiraja e lokalit', null, CostCategory::Rent],
        ];
    }

    #[DataProvider('invoiceLines')]
    public function test_links_invoice_text_to_its_price_driver(string $description, ?string $driverCode, CostCategory $category): void
    {
        $classification = (new KeywordExpenseClassifier)->classify($description);

        $this->assertNotNull($classification);
        $this->assertSame($driverCode, $classification->driverCode);
        $this->assertSame($category, $classification->category);
    }

    public function test_returns_null_for_text_it_cannot_place(): void
    {
        $this->assertNull((new KeywordExpenseClassifier)->classify('Shërbim kontabiliteti'));
    }
}
