<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CriticalSignalNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly NotificationDelivery $delivery) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->delivery->subject)
            ->greeting('SellerScope: важный сигнал')
            ->line($this->delivery->fact)
            ->line($this->delivery->recommendation)
            ->action('Открыть SellerScope', url($this->delivery->action_url ?? '/overview'));
    }
}
