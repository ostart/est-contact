<?php

namespace App\Providers;

use App\Models\SystemSetting;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Contact::observe(\App\Observers\ContactObserver::class);

        $this->applyMailConfigFromSettings();

        // Сессия MySQL в UTC — все TIMESTAMP пишутся и читаются в UTC
        if (config('database.default') === 'mysql') {
            try {
                \Illuminate\Support\Facades\DB::statement("SET time_zone = '+00:00'");
            } catch (\Throwable) {
                // SQLite и др. — игнорируем
            }
        }

        // Отображение дат в Filament по умолчанию — Москва (хранение остаётся UTC)
        FilamentTimezone::set('Europe/Moscow');
    }

    /**
     * Применяет настройки почты из раздела «Настройки» (суперадмин).
     * Документация Timeweb: https://timeweb.com/ru/docs/pochta/
     * Локально при MAIL_MAILER=log не переключаем на SMTP из БД — письма идут в лог.
     */
    private function applyMailConfigFromSettings(): void
    {
        try {
            if (config('mail.default') !== 'smtp') {
                return;
            }

            $host = SystemSetting::get('mail_host');
            if (blank($host)) {
                return;
            }

            config(['mail.default' => 'smtp']);
            config([
                'mail.mailers.smtp' => [
                    'transport' => 'smtp',
                    'url' => config('mail.mailers.smtp.url'),
                    'host' => $host,
                    'port' => (int) SystemSetting::get('mail_port', config('mail.mailers.smtp.port')),
                    'encryption' => SystemSetting::get('mail_encryption') ?: config('mail.mailers.smtp.encryption'),
                    'username' => SystemSetting::get('mail_username'),
                    'password' => SystemSetting::get('mail_password'),
                    'timeout' => null,
                    'local_domain' => config('mail.mailers.smtp.local_domain'),
                ],
            ]);

            $fromAddress = SystemSetting::get('mail_from_address');
            $fromName = SystemSetting::get('mail_from_name', config('mail.from.name'));
            if (filled($fromAddress)) {
                config(['mail.from' => [
                    'address' => $fromAddress,
                    'name' => $fromName,
                ]]);
            }
        } catch (\Throwable) {
            // Таблица system_settings может отсутствовать (миграции не выполнены)
        }
    }
}
