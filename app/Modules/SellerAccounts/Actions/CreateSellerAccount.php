<?php

namespace App\Modules\SellerAccounts\Actions;

use App\Models\User;
use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CreateSellerAccount
{
    public function handle(
        User $user,
        string $name,
        SellerAccountSource $source = SellerAccountSource::Demo,
        ?string $wbAccountId = null,
        string $timezone = 'Europe/Moscow',
    ): SellerAccount {
        if (! $user->hasVerifiedEmail()) {
            throw new AuthorizationException('Email verification is required.');
        }

        return DB::transaction(function () use ($user, $name, $source, $wbAccountId, $timezone): SellerAccount {
            $sellerAccount = $user->sellerAccounts()->create([
                'name' => $name,
                'source' => $source,
                'status' => SellerAccountStatus::Pending,
                'wb_account_id' => $wbAccountId,
                'timezone' => $timezone,
            ]);

            $preference = $user->preference()->firstOrCreate();

            if ($preference->active_seller_account_id === null) {
                $preference->update(['active_seller_account_id' => $sellerAccount->id]);
            }

            return $sellerAccount;
        });
    }
}
