<?php

namespace App\Notifications;

use App\Filament\Resources\ContactResource;
use App\Models\Contact;
use App\Models\SystemSetting;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Отправляется синхронно, чтобы уведомление сразу появилось в колокольчике.
 * При необходимости email можно вынести в отдельную очередь позже.
 */
class ContactAssignedNotification extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Contact $contact
    ) {
    }

    /**
     * Get the notification's delivery channels.
     * Email отправляется только если в настройках включена рассылка (Почтовый сервер → Включить рассылку).
     *
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

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Вам назначен новый контакт')
            ->greeting('Здравствуйте, ' . $notifiable->name . '!')
            ->line('Вам назначен новый контакт для обработки.')
            ->line('**Контакт:** ' . $this->contact->full_name)
            ->line('**Телефон:** ' . $this->contact->phone)
            ->when($this->contact->email, fn($mail) => $mail->line('**Email:** ' . $this->contact->email))
            ->when($this->contact->district, fn($mail) => $mail->line('**Округ:** ' . $this->contact->district))
            ->action('Просмотреть контакт', ContactResource::getUrl('view', ['record' => $this->contact]))
            ->line('Спасибо за использование нашего приложения!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $viewUrl = ContactResource::getUrl('view', ['record' => $this->contact]);

        $body = $this->contact->full_name . ' · ' . $this->contact->phone;
        if ($this->contact->district) {
            $body .= ' · ' . $this->contact->district;
        }

        return FilamentNotification::make()
            ->info()
            ->title('Вам назначен новый контакт')
            ->body($body)
            ->icon('heroicon-o-user-plus')
            ->actions([
                Action::make('view_contact')
                    ->label('Просмотреть')
                    ->button()
                    ->url($viewUrl),
                Action::make('mark_as_read')
                    ->label('Прочитано')
                    ->button()
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}

