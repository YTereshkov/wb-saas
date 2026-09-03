<?php

namespace App\Modules\SellerAccounts\Actions;

use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\SellerAccounts\Models\SellerAccountCredential;
use App\Modules\Synchronization\Enums\SyncRunStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StoreSellerAccountCredential
{
    /**
     * @param  list<string>  $permissions
     */
    public function handle(
        SellerAccount $sellerAccount,
        string $token,
        array $permissions = [],
        ?CarbonInterface $verifiedAt = null,
    ): SellerAccountCredential {
        if ($token === '') {
            throw new InvalidArgumentException('Credential token cannot be empty.');
        }

        return DB::transaction(function () use (
            $sellerAccount,
            $token,
            $permissions,
            $verifiedAt,
        ): SellerAccountCredential {
            $account = SellerAccount::query()->lockForUpdate()->findOrFail($sellerAccount->id);
            $credential = $account->credential()->lockForUpdate()->first();
            $fingerprint = SellerAccountCredential::fingerprint($token);

            if ($credential !== null && ! hash_equals($credential->fingerprint, $fingerprint)) {
                $account->syncRuns()
                    ->whereIn('status', [SyncRunStatus::Pending, SyncRunStatus::Running])
                    ->update([
                        'status' => SyncRunStatus::Failed,
                        'finished_at' => now(),
                        'error_code' => 'credential_replaced',
                        'error_summary' => 'Синхронизация остановлена после обновления токена.',
                    ]);
                $account->syncResourceStates()->delete();
            }

            $normalizedPermissions = array_values(array_unique($permissions));
            sort($normalizedPermissions);

            return $account->credential()->updateOrCreate([], [
                'token' => $token,
                'fingerprint' => $fingerprint,
                'permissions' => $normalizedPermissions,
                'verified_at' => $verifiedAt,
                'invalidated_at' => null,
            ]);
        });
    }
}
