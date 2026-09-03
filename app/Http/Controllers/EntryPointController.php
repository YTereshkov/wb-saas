<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EntryPointController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        $preference = $user->preference()->first();
        $account = $preference?->activeSellerAccount()->first()
            ?? $user->sellerAccounts()->oldest('id')->first();

        if ($account === null) {
            return redirect()->route('settings.cabinets.connect');
        }

        if ($account->status === SellerAccountStatus::Verified) {
            return redirect()->route('settings.cabinets.verification', $account);
        }

        if ($account->status === SellerAccountStatus::InitialSync) {
            return redirect()->route('settings.cabinets.initial-sync.show', $account);
        }

        return redirect()->route('overview');
    }
}
