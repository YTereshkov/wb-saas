<?php

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLoginLinkNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Одноразовый вход в SellerScope')
            ->greeting('Здравствуйте!')
            ->line('Используйте безопасную одноразовую ссылку для входа в SellerScope.')
            ->action('Войти в SellerScope', route('magic-link.consume', $this->token))
            ->line('Ссылка действует 15 минут и может быть использована только один раз.')
            ->line('Если вы не запрашивали вход, просто проигнорируйте письмо.');
    }
}
