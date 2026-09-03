<?php

namespace App\Modules\Synchronization\Queries;

use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Enums\SyncResource;
use App\Modules\Synchronization\Models\SyncResourceState;

final class InitialSyncStatusQuery
{
    /** @return array<string, mixed> */
    public function forAccount(SellerAccount $account): array
    {
        $run = $account->syncRuns()->latest('id')->first();
        /** @var array<string, SyncResourceState> $states */
        $states = [];

        foreach ($account->syncResourceStates()->get() as $state) {
            $states[$state->resource->value] = $state;
        }

        $resources = [];

        foreach ([
            SyncResource::Products,
            SyncResource::Orders,
            SyncResource::Sales,
            SyncResource::Stocks,
        ] as $resource) {
            $state = $states[$resource->value] ?? null;
            $resources[] = [
                'key' => $resource->value,
                'label' => match ($resource) {
                    SyncResource::Products => 'Товары',
                    SyncResource::Orders => 'Заказы',
                    SyncResource::Sales => 'Продажи',
                    SyncResource::Stocks => 'Остатки',
                },
                'status' => $state?->status->value ?? 'pending',
                'availability' => $state?->availability->value ?? 'unknown',
            ];
        }

        return [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'wbAccountId' => $account->displayWbAccountId(),
                'environment' => $account->environment(),
                'status' => $account->status->value,
            ],
            'run' => $run ? [
                'id' => $run->id,
                'status' => $run->status->value,
                'progress' => $run->progress,
                'errorSummary' => $run->error_summary,
            ] : null,
            'resources' => $resources,
            'completed' => $run?->status->value === 'completed',
        ];
    }
}
