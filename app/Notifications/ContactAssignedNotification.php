<?php

namespace App\Notifications;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Contact $contact
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
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
            ->action('Просмотреть контакт', url('/admin'))
            ->line('Спасибо за использование нашего приложения!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'contact_id' => $this->contact->id,
            'contact_name' => $this->contact->full_name,
            'contact_phone' => $this->contact->phone,
        ];
    }
}

