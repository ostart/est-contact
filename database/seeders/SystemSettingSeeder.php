<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'contact_processing_timeout_days', 'value' => '30'],
            // Почтовый сервер (для уведомлений при назначении контакта). Документация Timeweb: https://timeweb.com/ru/docs/pochta/
            ['key' => 'mail_notifications_enabled', 'value' => '0'],
            ['key' => 'mail_host', 'value' => ''],
            ['key' => 'mail_port', 'value' => '465'],
            ['key' => 'mail_encryption', 'value' => 'ssl'],
            ['key' => 'mail_username', 'value' => ''],
            ['key' => 'mail_password', 'value' => ''],
            ['key' => 'mail_from_address', 'value' => ''],
            ['key' => 'mail_from_name', 'value' => 'Есть Контакт'],
        ];

        foreach ($settings as $setting) {
            \App\Models\SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }

        $this->command->info('System settings created successfully!');
    }
}
