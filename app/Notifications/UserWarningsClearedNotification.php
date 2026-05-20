<?php

namespace App\Notifications;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserWarningsClearedNotification extends Notification
{
    public function __construct(
        public bool $hadWarnings = true,
        public bool $hadBan = false,
    ) {
    }

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
        $mail = (new MailMessage)
            ->subject($this->mailSubject())
            ->greeting('Здравствуйте, ' . $notifiable->name . '!');

        foreach ($this->descriptionLines() as $line) {
            $mail->line($line);
        }

        return $mail
            ->line('Продолжайте соблюдать правила работы в системе.')
            ->salutation('С уважением, Администрация «БВ Контакт»');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->success()
            ->title($this->databaseTitle())
            ->body(implode(' ', $this->descriptionLines()))
            ->icon('heroicon-o-check-badge')
            ->actions([
                Action::make('mark_as_read')
                    ->label('Прочитано')
                    ->button()
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    private function mailSubject(): string
    {
        return match (true) {
            $this->hadWarnings && $this->hadBan => 'Предупреждения сняты, аккаунт разблокирован',
            $this->hadBan => 'Ваш аккаунт разблокирован',
            default => 'Предупреждения сняты',
        };
    }

    private function databaseTitle(): string
    {
        return match (true) {
            $this->hadWarnings && $this->hadBan => 'Предупреждения сняты, доступ восстановлен',
            $this->hadBan => 'Ваш аккаунт разблокирован',
            default => 'Предупреждения сняты',
        };
    }

    /**
     * @return array<int, string>
     */
    private function descriptionLines(): array
    {
        return match (true) {
            $this->hadWarnings && $this->hadBan => [
                'Администратор снял все предупреждения и блокировку вашего аккаунта в системе «БВ Контакт».',
                'Доступ к системе восстановлен.',
            ],
            $this->hadBan => [
                'Блокировка вашего аккаунта в системе «БВ Контакт» была снята администратором.',
                'Доступ к системе восстановлен.',
            ],
            default => [
                'Все предупреждения по вашему аккаунту в системе «БВ Контакт» были сняты администратором.',
            ],
        };
    }
}
