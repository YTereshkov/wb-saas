<?php

namespace App\Modules\SellerAccounts\Queries;

use App\Models\User;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\SellerAccounts\Models\SellerAccountCredential;

final class CabinetListQuery
{
    /** @return array{items: list<array<string, mixed>>, count: int} */
    public function forUser(User $user): array
    {
        $accounts = $user->sellerAccounts()
            ->whereNot('status', SellerAccountStatus::Disconnected)
            ->with(['credential', 'syncRuns' => fn ($query) => $query->latest('id')->limit(1)])
            ->orderBy('name')
            ->get();

        return [
            'items' => array_values($accounts->map(function (SellerAccount $account): array {
                $credential = $account->getRelation('credential');

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'wbAccountId' => $account->displayWbAccountId(),
                    'environment' => $account->environment(),
                    'status' => $account->status->value,
                    'statusLabel' => $this->statusLabel($account->status->value),
                    'lastSyncAt' => $account->last_sync_completed_at?->toISOString(),
                    'lastSyncLabel' => $account->last_sync_completed_at
                        ? $account->last_sync_completed_at->setTimezone($account->timezone)->format('d.m.Y, H:i')
                        : 'Ещё не обновлялся',
                    'permissions' => $credential instanceof SellerAccountCredential
                        ? ($credential->permissions ?? [])
                        : [],
                    'syncProgress' => $account->syncRuns->first()?->progress,
                ];
            })->all()),
            'count' => $accounts->count(),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Подключён',
            'verified' => 'Проверен',
            'initial_sync' => 'Загружаем данные',
            'partial' => 'Частично загружен',
            'invalid_credentials' => 'Нужен новый токен',
            'disconnected' => 'Отключён',
            default => 'Ожидает подключения',
        };
    }
}
