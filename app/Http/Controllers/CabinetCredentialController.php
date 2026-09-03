<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCabinetCredentialRequest;
use App\Modules\SellerAccounts\Actions\UpdateSellerAccountCredential;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Http\RedirectResponse;

class CabinetCredentialController extends Controller
{
    public function update(
        UpdateCabinetCredentialRequest $request,
        SellerAccount $cabinet,
        UpdateSellerAccountCredential $action,
    ): RedirectResponse {
        $action->handle($cabinet, $request->validated('token'));

        return back()->with('status', 'credential-updated');
    }
}
