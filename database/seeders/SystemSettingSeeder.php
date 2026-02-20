<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Настройки почты берутся из config (который читает .env при загрузке) или из env().
     * После записи кэш system_settings сбрасывается.
     */
    public function run(): void
    {
        $smtp = config('mail.mailers.smtp', []);
        $from = config('mail.from', []);

        $settings = [
            ['key' => 'contact_processing_timeout_days', 'value' => (string) (env('SEED_CONTACT_TIMEOUT_DAYS') ?? 30)],
            ['key' => 'mail_notifications_enabled', 'value' => filter_var(env('SEED_MAIL_NOTIFICATIONS_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN) ? '1' : '0'],
            ['key' => 'mail_host', 'value' => (string) ($smtp['host'] ?? env('MAIL_HOST', ''))],
            ['key' => 'mail_port', 'value' => (string) ($smtp['port'] ?? env('MAIL_PORT', '465'))],
            ['key' => 'mail_encryption', 'value' => (string) ($smtp['encryption'] ?? env('MAIL_ENCRYPTION', 'ssl'))],
            ['key' => 'mail_username', 'value' => (string) ($smtp['username'] ?? env('MAIL_USERNAME', ''))],
            ['key' => 'mail_password', 'value' => (string) ($smtp['password'] ?? env('MAIL_PASSWORD', ''))],
            ['key' => 'mail_from_address', 'value' => (string) ($from['address'] ?? env('MAIL_FROM_ADDRESS', ''))],
            ['key' => 'mail_from_name', 'value' => (string) ($from['name'] ?? env('MAIL_FROM_NAME', config('app.name')))],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }

        Cache::forget('system_settings');
        $this->command->info('System settings created successfully!');
    }
}
