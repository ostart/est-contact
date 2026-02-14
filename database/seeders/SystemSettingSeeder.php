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
            ['key' => 'contact_processing_timeout', 'value' => '30'],
        ];

        foreach ($settings as $setting) {
            \App\Models\SystemSetting::create($setting);
        }

        $this->command->info('System settings created successfully!');
    }
}
