<?php

namespace App\Services\Margin\Simulation;

use App\Enums\OfferKind;
use App\Models\SupplierOffer;
use App\Services\Margin\CompanyProfile;
use App\Services\Margin\CostLineProfile;
use App\Services\Margin\Simulation\Actions\BuyAhead;
use App\Services\Margin\Simulation\Actions\FixedPriceContract;
use App\Services\Margin\Simulation\Actions\RaisePrices;
use App\Services\Margin\Simulation\Actions\SimulationAction;
use App\Services\Margin\Simulation\Actions\SwitchSupplier;
use Illuminate\Validation\ValidationException;

/**
 * Builds simulation actions from validated what-if requests.
 */
final class SimulationActionFactory
{
    public const array Types = ['raise_prices', 'switch_supplier', 'fixed_price', 'buy_ahead'];

    /**
     * @param  array<string, mixed>  $definition
     */
    public function make(array $definition, CompanyProfile $profile, int $index): SimulationAction
    {
        return match ($definition['type']) {
            'raise_prices' => $this->raisePrices($definition),
            'switch_supplier' => $this->fromOffer($definition, $profile, $index, OfferKind::AlternativeSupplier),
            'fixed_price' => $this->fromOffer($definition, $profile, $index, OfferKind::FixedPrice),
            'buy_ahead' => $this->buyAhead($definition, $profile, $index),
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function raisePrices(array $definition): RaisePrices
    {
        $steps = [];

        foreach ($definition['steps'] as $step) {
            $month = (int) $step['month'];
            $steps[$month] = (($steps[$month] ?? 0.0) + 1) * (1 + $step['percent'] / 100) - 1;
        }

        ksort($steps);

        return new RaisePrices($steps);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function fromOffer(array $definition, CompanyProfile $profile, int $index, OfferKind $kind): SimulationAction
    {
        $offer = SupplierOffer::query()
            ->with('supplier')
            ->where('company_id', $profile->company->id)
            ->find($definition['offer_id']);

        if ($offer === null || $offer->kind !== $kind) {
            throw ValidationException::withMessages(["actions.{$index}.offer_id" => "The selected offer is not a {$kind->value} offer of this company."]);
        }

        $line = $this->line($profile, $offer->cost_line_id, $index, 'offer_id');
        $ratio = $offer->unit_price / $line->latestUnitPrice;

        if ($kind === OfferKind::FixedPrice) {
            return new FixedPriceContract($line->costLine->id, $ratio, $offer->duration_months ?? (int) config('margin.simulation.horizon_months'), $offer->id, $offer->supplier->name);
        }

        $share = isset($definition['share_percent']) ? min($offer->max_share, $definition['share_percent'] / 100) : $offer->max_share;

        return new SwitchSupplier($line->costLine->id, $share, $ratio, $offer->id, $offer->supplier->name);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function buyAhead(array $definition, CompanyProfile $profile, int $index): BuyAhead
    {
        $line = $this->line($profile, (int) $definition['cost_line_id'], $index, 'cost_line_id');

        if (! $line->costLine->storable) {
            throw ValidationException::withMessages(["actions.{$index}.cost_line_id" => 'This cost line is not marked as storable.']);
        }

        return new BuyAhead($line->costLine->id, (int) ($definition['months'] ?? 3), $line->costLine->storage_cost_rate);
    }

    private function line(CompanyProfile $profile, int $costLineId, int $index, string $field): CostLineProfile
    {
        $line = $profile->line($costLineId);

        if ($line === null || $line->latestUnitPrice <= 0) {
            throw ValidationException::withMessages(["actions.{$index}.{$field}" => 'This cost line has no recent purchases to simulate.']);
        }

        return $line;
    }
}
