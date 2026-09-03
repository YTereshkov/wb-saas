<?php

namespace App\Modules\Notifications\Queries;

use App\Models\User;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\NotificationEvents;
use App\Modules\SellerAccounts\Models\SellerAccount;

final class NotificationSettingsQuery
{
    /** @return array<string, mixed> */
    public function forAccount(User $user, SellerAccount $account): array
    {
        $preferences = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('seller_account_id', $account->id)
            ->where('channel', 'email')
            ->get()
            ->keyBy('event');

        return [
            'cabinet' => ['id' => $account->id, 'name' => $account->name],
            'email' => $user->email,
            'emailVerified' => $user->hasVerifiedEmail(),
            'emailEnabled' => $preferences->get('_email_channel') instanceof NotificationPreference
                ? $preferences->get('_email_channel')->enabled
                : true,
            'events' => collect(NotificationEvents::DEFINITIONS)->map(function (array $definition, string $event) use ($preferences): array {
                $preference = $preferences->get($event);
                $stored = $preference instanceof NotificationPreference ? $preference : null;

                return [
                    'event' => $event,
                    ...$definition,
                    'enabled' => $stored instanceof NotificationPreference ? $stored->enabled : $event !== 'daily_digest',
                    'threshold' => $stored instanceof NotificationPreference ? $stored->threshold : $definition['threshold'],
                    'frequency' => $stored instanceof NotificationPreference ? $stored->frequency : ($event === 'daily_digest' ? 'daily' : null),
                ];
            })->values()->all(),
        ];
    }
}
