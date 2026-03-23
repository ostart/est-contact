<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;

/**
 * Письмо верификации email с ссылкой на маршрут приложения.
 * Всегда уходит по каналу mail, независимо от настройки «Включить рассылку уведомлений»
 * (mail_notifications_enabled) — эта опция относится только к служебным уведомлениям в панели.
 * Без ShouldQueue — отправляется сразу, при MAIL_MAILER=log попадает в лог без queue:work.
 */
class VerifyEmail extends BaseVerifyEmail
{
    public string $url;

    /**
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    protected function verificationUrl($notifiable): string
    {
        return $this->url;
    }
}
