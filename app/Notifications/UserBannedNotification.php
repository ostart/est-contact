<?php

namespace App\Notifications;

use App\Models\SystemSetting;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserBannedNotification extends Notification
{
    public function __construct(
        public ?string $reason = null
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
        $mail = (new MailMessage)
            ->subject('Ваш аккаунт заблокирован')
            ->greeting('Здравствуйте, ' . $notifiable->name . '!')
            ->line('Ваш аккаунт в системе «Есть Контакт» был заблокирован администратором.');

        if ($this->reason) {
            $mail->line('**Причина блокировки:**')
                ->line($this->reason);
        }

        return $mail
            ->line('Доступ к системе ограничен. Если вы считаете, что это ошибка, обратитесь к администратору.')
            ->salutation('С уважением, Администрация «Есть Контакт»');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->danger()
            ->title('Ваш аккаунт заблокирован')
            ->body($this->reason ?: 'Обратитесь к администратору за подробностями.')
            ->icon('heroicon-o-no-symbol')
            ->actions([
                Action::make('mark_as_read')
                    ->label('Прочитано')
                    ->button()
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
