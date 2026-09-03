<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnalyticsContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'seller_account_id' => [
                'nullable',
                'integer',
                Rule::exists('seller_accounts', 'id')->where('user_id', $this->user()->id),
            ],
            'period_preset' => ['required', Rule::in([
                'july_2026',
                'june_2026',
                'last_30_days',
                'current_month',
                'previous_month',
                'custom',
            ])],
            'period_start' => ['nullable', 'required_if:period_preset,custom', 'date_format:Y-m-d'],
            'period_end' => ['nullable', 'required_if:period_preset,custom', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'return_to' => ['required', 'string', 'max:2048', 'regex:/^\/(?!\/)/'],
        ];
    }
}
