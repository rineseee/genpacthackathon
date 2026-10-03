<?php

namespace App\Http\Requests;

use App\Enums\AlertMetric;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAlertRuleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'threshold' => ['sometimes', 'numeric', 'min:0', $this->route('alert_rule')->metric === AlertMetric::StressProbability ? 'max:1' : 'max:100000000'],
            'recipient_email' => ['sometimes', 'email', 'max:255'],
        ];
    }
}
