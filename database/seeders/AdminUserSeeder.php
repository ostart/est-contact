<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $name = config('seeding.admin_name');
        $email = config('seeding.admin_email');
        $password = config('seeding.admin_password');

        $admin = \App\Models\User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
            'email_verified_at' => now(),
            'is_approved' => true,
            'has_dashboard_access' => true,
        ]);

        // Назначаем все роли администратору
        $admin->assignRole(['leader', 'manager', 'administrator', 'superadmin']);

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: ' . $email);
    }
}
