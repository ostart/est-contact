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

    public function mount(): void
    {
        if (! auth()->user()->has_dashboard_access) {
            $this->redirect(auth()->user()->getFilamentHomeUrl());
        }
    }

    public function mountCanAuthorizeAccess(): void
    {
        if (! auth()->user()->has_dashboard_access) {
            return;
        }

        abort_unless(static::canAccess(), 403);
    }
}
