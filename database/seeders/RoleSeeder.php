<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'leader', 'guard_name' => 'web'],
            ['name' => 'manager', 'guard_name' => 'web'],
            ['name' => 'administrator', 'guard_name' => 'web'],
            ['name' => 'superadmin', 'guard_name' => 'web'],
        ];

        foreach ($roles as $role) {
            \Spatie\Permission\Models\Role::create($role);
        }

        $this->command->info('Roles created successfully!');
    }
}
