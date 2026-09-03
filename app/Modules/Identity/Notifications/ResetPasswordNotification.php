<?php

namespace App\Modules\Identity\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
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

    /**
     * @param  User  $notifiable
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Сброс пароля SellerScope')
            ->greeting('Здравствуйте!')
            ->line('Мы получили запрос на создание нового пароля для вашего аккаунта.')
            ->action('Создать новый пароль', $url)
            ->line('Ссылка действует 30 минут.')
            ->line('Если вы не запрашивали сброс пароля, просто проигнорируйте письмо.');
    }
}
