<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->has_dashboard_access;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
