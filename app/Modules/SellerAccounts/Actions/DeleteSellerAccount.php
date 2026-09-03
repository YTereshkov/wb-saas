<?php

namespace App\Modules\SellerAccounts\Actions;

use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Support\Facades\DB;

final class DeleteSellerAccount
{
    public function handle(SellerAccount $account): void
    {
        DB::transaction(function () use ($account): void {
            $lockedAccount = SellerAccount::query()->lockForUpdate()->findOrFail($account->id);
            $preference = $lockedAccount->user->preference;

            if ($preference?->active_seller_account_id === $lockedAccount->id) {
                $replacement = $lockedAccount->user->sellerAccounts()
                    ->whereKeyNot($lockedAccount->id)
                    ->orderBy('name')
                    ->first();
                $preference->update(['active_seller_account_id' => $replacement?->id]);
            }

            $lockedAccount->delete();
        });
    }
}
