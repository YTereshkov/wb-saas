<?php

namespace App\Modules\Identity\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * @param  User  $notifiable
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        return (new MailMessage)
            ->subject('Подтвердите почту в SellerScope')
            ->greeting('Здравствуйте!')
            ->line('Подтвердите адрес электронной почты, чтобы продолжить настройку SellerScope.')
            ->action('Подтвердить почту', $url)
            ->line('Если вы не создавали аккаунт, просто проигнорируйте письмо.');
    }
}
