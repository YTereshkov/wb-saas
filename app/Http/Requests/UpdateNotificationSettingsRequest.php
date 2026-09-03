<?php

namespace App\Http\Requests;

use App\Modules\Notifications\NotificationEvents;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasVerifiedEmail() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'seller_account_id' => ['required', 'integer', Rule::exists('seller_accounts', 'id')->where('user_id', $this->user()->id)],
            'email_enabled' => ['required', 'boolean'],
            'events' => ['required', 'array:'.implode(',', array_keys(NotificationEvents::DEFINITIONS))],
            'events.*.enabled' => ['required', 'boolean'],
            'events.*.threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'events.*.frequency' => ['nullable', Rule::in(['daily', 'weekly'])],
        ];
    }
}
