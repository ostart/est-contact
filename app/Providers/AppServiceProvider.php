<?php

namespace App\Providers;

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
}
