<?php

namespace App\Http\Requests;

use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCabinetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cabinet = $this->route('cabinet');

        return $cabinet instanceof SellerAccount && $this->user()?->can('update', $cabinet) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120']];
    }
}
