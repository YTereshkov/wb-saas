<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Analytics\Models\ProductSignal;
use App\Modules\Notifications\Jobs\SendNotificationDelivery;
use App\Modules\Notifications\Models\NotificationDelivery;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\NotificationEvents;
use App\Modules\SellerAccounts\Models\SellerAccount;

final class QueueSignalNotifications
{
    public function handle(SellerAccount $account): void
    {
        $preferences = NotificationPreference::query()
            ->where('user_id', $account->user_id)
            ->where('seller_account_id', $account->id)
            ->where('channel', 'email')
            ->get()
            ->keyBy('event');
        $channelPreference = $preferences->get('_email_channel');
        if ($channelPreference instanceof NotificationPreference && ! $channelPreference->enabled) {
            return;
        }

        $account->productSignals()->with('product')->orderBy('priority')->limit(10)->get()
            ->each(function (ProductSignal $signal) use ($account, $preferences): void {
                $event = $this->eventForSignal($signal->type);
                $preference = $event === null ? null : $preferences->get($event);
                if ($event === null
                    || ($preference instanceof NotificationPreference && ! $preference->enabled)
                    || ! $this->passesThreshold($signal, $event, $preference)) {
                    return;
                }

                $delivery = NotificationDelivery::query()->firstOrCreate([
                    'user_id' => $account->user_id,
                    'seller_account_id' => $account->id,
                    'product_signal_id' => $signal->id,
                    'event' => $event,
                    'channel' => 'email',
                ], [
                    'status' => 'pending',
                    'subject' => 'SellerScope: '.$signal->fact,
                    'fact' => $signal->fact,
                    'recommendation' => $signal->recommendation,
                    'action_url' => '/products/'.$signal->product_id,
                    'queued_at' => now(),
                ]);

                if ($delivery->wasRecentlyCreated || $delivery->status === 'failed') {
                    if ($delivery->status === 'failed') {
                        $delivery->update([
                            'status' => 'pending',
                            'error_code' => null,
                            'queued_at' => now(),
                        ]);
                    }

                    SendNotificationDelivery::dispatch($delivery->id)->onQueue('notifications');
                }
            });
    }

    private function passesThreshold(
        ProductSignal $signal,
        string $event,
        mixed $preference,
    ): bool {
        $configured = $preference instanceof NotificationPreference
            ? $preference->threshold
            : null;
        $threshold = $configured ?? NotificationEvents::DEFINITIONS[$event]['threshold'];
        if ($threshold === null) {
            return true;
        }

        $evidence = $signal->evidence;
        $value = match ($event) {
            'low_stock' => $evidence['stock_coverage_days'] ?? null,
            'sales_decline' => $evidence['revenue_decline_percent'] ?? null,
            'returns_growth' => $this->returnsRiskValue($evidence),
            default => null,
        };
        if (! is_numeric($value)) {
            return false;
        }

        return $event === 'low_stock'
            ? (float) $value <= $threshold
            : (float) $value >= $threshold;
    }

    /** @param array<string, mixed> $evidence */
    private function returnsRiskValue(array $evidence): int|float|null
    {
        if (is_numeric($evidence['returns_rate'] ?? null)) {
            return (float) $evidence['returns_rate'];
        }

        $current = $evidence['buyout_rate'] ?? null;
        $comparison = $evidence['comparison_buyout_rate'] ?? null;

        return is_numeric($current) && is_numeric($comparison)
            ? max(0, (float) $comparison - (float) $current)
            : null;
    }

    private function eventForSignal(string $type): ?string
    {
        return match (true) {
            str_starts_with($type, 'stock_'), $type === 'out_of_stock' => 'low_stock',
            $type === 'revenue_decline' => 'sales_decline',
            $type === 'buyout_returns_risk' => 'returns_growth',
            default => null,
        };
    }
}
