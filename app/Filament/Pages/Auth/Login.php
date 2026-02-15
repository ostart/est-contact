<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();
        
        // Проверяем параметр locale в URL
        if (request()->has('locale')) {
            session(['locale' => request()->get('locale')]);
        }
    }
}
