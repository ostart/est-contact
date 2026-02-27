<?php

namespace App\Notifications;

use App\Models\SystemSetting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserUnbannedNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (filter_var(SystemSetting::get('mail_notifications_enabled', '0'), FILTER_VALIDATE_BOOLEAN)) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ваш аккаунт разблокирован')
            ->greeting('Здравствуйте, ' . $notifiable->name . '!')
            ->line('Ваш аккаунт в системе «Есть Контакт» был разблокирован.')
            ->line('Доступ к системе восстановлен. Вы можете войти и продолжить работу.')
            ->action('Войти в систему', url('/admin/login'))
            ->salutation('С уважением, Администрация «Есть Контакт»');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'format' => 'filament',
            'title' => 'Ваш аккаунт разблокирован',
            'body' => 'Доступ к системе восстановлен.',
            'icon' => 'heroicon-o-check-circle',
            'iconColor' => 'success',
        ];
    }
}
