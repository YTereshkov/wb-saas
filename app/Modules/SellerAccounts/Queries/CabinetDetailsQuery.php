<?php

namespace App\Modules\SellerAccounts\Queries;

use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\SellerAccounts\Models\SellerAccountCredential;
use App\Modules\Synchronization\Models\SyncRun;

final class CabinetDetailsQuery
{
    /** @return array<string, mixed> */
    public function forAccount(SellerAccount $account): array
    {
        $account->load(['credential', 'syncRuns' => fn ($query) => $query->latest('id')->limit(10)]);
        $credential = $account->getRelation('credential');

        return [
            'id' => $account->id,
            'name' => $account->name,
            'wbAccountId' => $account->displayWbAccountId(),
            'environment' => $account->environment(),
            'status' => $account->status->value,
            'connected' => $account->status->value !== 'disconnected',
            'lastSyncLabel' => $account->last_sync_completed_at?->setTimezone($account->timezone)->format('d.m.Y, H:i') ?? 'Ещё не обновлялся',
            'credential' => [
                'valid' => $credential instanceof SellerAccountCredential && $credential->invalidated_at === null,
                'permissions' => $credential instanceof SellerAccountCredential ? ($credential->permissions ?? []) : [],
                'verifiedAt' => $credential instanceof SellerAccountCredential ? $credential->verified_at?->toISOString() : null,
                'verifiedLabel' => $credential instanceof SellerAccountCredential
                    ? $credential->verified_at?->setTimezone($account->timezone)->format('d.m.Y, H:i')
                    : null,
            ],
            'syncRuns' => $account->syncRuns->map(fn (SyncRun $run): array => [
                'id' => $run->id,
                'type' => $run->type->value,
                'status' => $run->status->value,
                'progress' => $run->progress,
                'startedAt' => $run->started_at?->toISOString(),
                'startedLabel' => $run->started_at?->setTimezone($account->timezone)->format('d.m.Y, H:i') ?? 'Ожидает запуска',
                'finishedLabel' => $run->finished_at?->setTimezone($account->timezone)->format('d.m.Y, H:i'),
                'errorSummary' => $run->error_summary,
            ])->values()->all(),
        ];
    }
}
