<?php

namespace App\Modules\Notifications\Actions;

use App\Models\User;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\NotificationEvents;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Support\Facades\DB;

final class UpdateNotificationSettings
{
    /** @param array<string, array{enabled: bool, threshold?: int|null, frequency?: string|null}> $events */
    public function handle(User $user, SellerAccount $account, array $events, bool $emailEnabled = true): void
    {
        DB::transaction(function () use ($user, $account, $events, $emailEnabled): void {
            NotificationPreference::query()->updateOrCreate([
                'user_id' => $user->id,
                'seller_account_id' => $account->id,
                'event' => '_email_channel',
                'channel' => 'email',
            ], ['enabled' => $emailEnabled, 'threshold' => null, 'frequency' => null]);

            foreach (NotificationEvents::DEFINITIONS as $event => $definition) {
                $values = $events[$event] ?? ['enabled' => false];
                NotificationPreference::query()->updateOrCreate([
                    'user_id' => $user->id,
                    'seller_account_id' => $account->id,
                    'event' => $event,
                    'channel' => 'email',
                ], [
                    'enabled' => (bool) $values['enabled'],
                    'threshold' => $definition['threshold'] === null ? null : ($values['threshold'] ?? $definition['threshold']),
                    'frequency' => $event === 'daily_digest' ? ($values['frequency'] ?? 'daily') : null,
                ]);
            }
        });
    }
}
