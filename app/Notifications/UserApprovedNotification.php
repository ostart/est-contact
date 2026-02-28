<?php

namespace App\Notifications;

use App\Models\SystemSetting;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserApprovedNotification extends Notification
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
            ->subject('Доступ в систему разрешен')
            ->greeting('Здравствуйте, ' . $notifiable->name . '!')
            ->line('Администратор одобрил вашу заявку на регистрацию в системе «Есть Контакт».')
            ->line('Теперь вы можете войти в систему и начать работу.')
            ->action('Войти в систему', url('/admin'))
            ->salutation('С уважением, Администрация «Есть Контакт»');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->success()
            ->title('Доступ в систему разрешен')
            ->body('Администратор одобрил вашу заявку. Теперь вы можете пользоваться системой.')
            ->icon('heroicon-o-check-circle')
            ->actions([
                Action::make('mark_as_read')
                    ->label('Прочитано')
                    ->button()
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
