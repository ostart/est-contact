<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Данные администратора при сидинге (php artisan db:seed)
    |--------------------------------------------------------------------------
    */
    'admin_name' => env('SEED_ADMIN_NAME', 'Admin'),
    'admin_email' => env('SEED_ADMIN_EMAIL', 'admin@example.com'),
    'admin_password' => env('SEED_ADMIN_PASSWORD', 'password'),
];
