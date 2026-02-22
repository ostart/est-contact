<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;

/**
 * Письмо верификации email с ссылкой на маршрут Filament.
 * Без ShouldQueue — отправляется сразу, при MAIL_MAILER=log попадает в лог без queue:work.
 */
class VerifyEmail extends BaseVerifyEmail
{
    public string $url;

    protected function verificationUrl($notifiable): string
    {
        return $this->url;
    }
}
