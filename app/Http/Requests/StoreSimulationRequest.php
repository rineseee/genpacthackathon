<?php

namespace App\Http\Requests;

use App\Enums\SimulationScenario;
use App\Models\PriceDriver;
use App\Services\Margin\Simulation\SimulationActionFactory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A what-if question: a scenario, optional driver shocks in percent, and actions to test.
 */
class StoreSimulationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scenario' => ['sometimes', Rule::enum(SimulationScenario::class)->only([SimulationScenario::Baseline, SimulationScenario::Replay2022])],
            'shocks' => ['sometimes', 'array'],
            'shocks.*' => ['numeric', 'between:-90,300'],
            'actions' => ['sometimes', 'array', 'max:8'],
            'actions.*.type' => ['required', Rule::in(SimulationActionFactory::Types)],
            'actions.*.steps' => ['required_if:actions.*.type,raise_prices', 'array', 'min:1', 'max:6'],
            'actions.*.steps.*.month' => ['required', 'integer', 'between:1,6'],
            'actions.*.steps.*.percent' => ['required', 'numeric', 'between:-30,50'],
            'actions.*.offer_id' => ['required_if:actions.*.type,switch_supplier,fixed_price', 'integer'],
            'actions.*.share_percent' => ['sometimes', 'numeric', 'between:1,100'],
            'actions.*.cost_line_id' => ['required_if:actions.*.type,buy_ahead', 'integer'],
            'actions.*.months' => ['sometimes', 'integer', 'between:1,6'],
            'paths' => ['sometimes', 'integer', 'between:500,10000'],
            'seed' => ['sometimes', 'integer', 'between:0,4294967295'],
        ];
    }

    /**
     * Shock keys must be known driver codes.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $codes = array_keys((array) $this->input('shocks', []));
                $unknown = array_diff($codes, PriceDriver::query()->whereIn('code', $codes)->pluck('code')->all());

                foreach ($unknown as $code) {
                    $validator->errors()->add('shocks', "Unknown price driver [{$code}].");
                }
            },
        ];
    }
}
