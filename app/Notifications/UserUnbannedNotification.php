<?php

namespace App\Notifications;

use App\Filament\Support\DatabaseNotificationActions;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
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
        if ($notifiable instanceof User && $notifiable->shouldReceiveMailNotifications()) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ваш аккаунт разблокирован')
            ->greeting('Здравствуйте, '.$notifiable->name.'!')
            ->line('Ваш аккаунт в системе «БВ Контакт» был разблокирован.')
            ->line('Доступ к системе восстановлен. Вы можете войти и продолжить работу.')
            ->action('Войти в систему', url('/admin/login'))
            ->salutation('С уважением, Администрация «БВ Контакт»');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->success()
            ->title('Ваш аккаунт разблокирован')
            ->body('Доступ к системе восстановлен.')
            ->icon('heroicon-o-check-circle')
            ->actions([
                DatabaseNotificationActions::markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
