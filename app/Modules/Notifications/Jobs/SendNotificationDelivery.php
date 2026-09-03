<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Models\NotificationDelivery;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\Notifications\CriticalSignalNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendNotificationDelivery implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(): void
    {
        $delivery = NotificationDelivery::query()->findOrFail($this->deliveryId);
        if (! $this->isEnabled($delivery)) {
            NotificationDelivery::query()
                ->whereKey($delivery->id)
                ->whereIn('status', ['pending', 'failed'])
                ->update(['status' => 'suppressed', 'error_code' => null]);

            return;
        }

        $claimed = NotificationDelivery::query()
            ->whereKey($delivery->id)
            ->whereIn('status', ['pending', 'failed'])
            ->update(['status' => 'sending', 'error_code' => null]);
        if ($claimed !== 1) {
            return;
        }

        $delivery->refresh()->load('user');

        try {
            $delivery->user->notifyNow(new CriticalSignalNotification($delivery));
        } catch (Throwable $exception) {
            NotificationDelivery::query()
                ->whereKey($delivery->id)
                ->where('status', 'sending')
                ->update(['status' => 'failed', 'error_code' => 'mail_delivery_failed']);

            throw $exception;
        }

        NotificationDelivery::query()
            ->whereKey($delivery->id)
            ->where('status', 'sending')
            ->update(['status' => 'sent', 'sent_at' => now(), 'error_code' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        NotificationDelivery::query()
            ->whereKey($this->deliveryId)
            ->whereIn('status', ['pending', 'failed'])
            ->update(['status' => 'failed', 'error_code' => 'mail_delivery_failed']);
    }

    private function isEnabled(NotificationDelivery $delivery): bool
    {
        $preferences = NotificationPreference::query()
            ->where('user_id', $delivery->user_id)
            ->where('seller_account_id', $delivery->seller_account_id)
            ->where('channel', $delivery->channel)
            ->whereIn('event', ['_email_channel', $delivery->event])
            ->get()
            ->keyBy('event');

        foreach (['_email_channel', $delivery->event] as $event) {
            $preference = $preferences->get($event);
            if ($preference instanceof NotificationPreference && ! $preference->enabled) {
                return false;
            }
        }

        return true;
    }
}
