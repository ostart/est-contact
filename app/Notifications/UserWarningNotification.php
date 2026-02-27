<?php

namespace App\Notifications;

use App\Models\SystemSetting;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserWarningNotification extends Notification
{
    public function __construct(
        public string $message
    ) {
    }

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
            ->subject('Предупреждение от администратора')
            ->greeting('Здравствуйте, ' . $notifiable->name . '!')
            ->line('Вы получили предупреждение от администратора системы «Есть Контакт».')
            ->line('**Текст предупреждения:**')
            ->line($this->message)
            ->line('Пожалуйста, обратите внимание на данное предупреждение.')
            ->salutation('С уважением, Администрация «Есть Контакт»');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->warning()
            ->title('Предупреждение от администратора')
            ->body($this->message)
            ->icon('heroicon-o-exclamation-triangle')
            ->actions([
                Action::make('mark_as_read')
                    ->label('Прочитано')
                    ->button()
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
