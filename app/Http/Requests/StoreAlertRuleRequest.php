<?php

namespace App\Http\Requests;

use App\Enums\AlertMetric;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Thresholds: stress_probability is a fraction (0.4 = 40%), margin_at_risk is EUR over the next
 * quarter, supplier_overcharge is EUR per month.
 */
class StoreAlertRuleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'metric' => [
                'required',
                Rule::enum(AlertMetric::class),
                Rule::unique('alert_rules')->where('company_id', $this->route('company')->id),
            ],
            'threshold' => ['required', 'numeric', 'min:0', $this->input('metric') === AlertMetric::StressProbability->value ? 'max:1' : 'max:100000000'],
            'recipient_email' => ['required', 'email', 'max:255'],
        ];
    }
}
