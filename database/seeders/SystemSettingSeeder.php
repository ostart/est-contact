<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Настройки почты берутся из .env (MAIL_*), остальные — значения по умолчанию.
     */
    public function run(): void
    {
        $mailFromName = config('mail.from.name', env('MAIL_FROM_NAME', config('app.name')));

        $settings = [
            ['key' => 'contact_processing_timeout_days', 'value' => (string) env('SEED_CONTACT_TIMEOUT_DAYS', 30)],
            // Почтовый сервер: при сидинге подставляются значения из .env (MAIL_*)
            ['key' => 'mail_notifications_enabled', 'value' => filter_var(env('SEED_MAIL_NOTIFICATIONS_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN) ? '1' : '0'],
            ['key' => 'mail_host', 'value' => (string) env('MAIL_HOST', '')],
            ['key' => 'mail_port', 'value' => (string) env('MAIL_PORT', '465')],
            ['key' => 'mail_encryption', 'value' => (string) env('MAIL_ENCRYPTION', 'ssl')],
            ['key' => 'mail_username', 'value' => (string) env('MAIL_USERNAME', '')],
            ['key' => 'mail_password', 'value' => (string) env('MAIL_PASSWORD', '')],
            ['key' => 'mail_from_address', 'value' => (string) env('MAIL_FROM_ADDRESS', '')],
            ['key' => 'mail_from_name', 'value' => (string) $mailFromName],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }

        $this->command->info('System settings created successfully!');
    }
}
