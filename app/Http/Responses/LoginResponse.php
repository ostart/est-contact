<?php

namespace App\Http\Responses;

use App\Filament\Resources\ContactResource;
use App\Filament\Resources\ManagementResource;
use App\Filament\Resources\UserResource;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = auth()->user();

        if ($user->has_dashboard_access) {
            return redirect()->intended(Filament::getUrl());
        }

        if ($user->hasRole('leader')) {
            return redirect()->to(ContactResource::getUrl('index'));
        }

        if ($user->hasRole('manager')) {
            return redirect()->to(ManagementResource::getUrl('index'));
        }

        if ($user->hasRole('superadmin')) {
            return redirect()->to(UserResource::getUrl('index'));
        }

        return redirect()->intended(Filament::getUrl());
    }
}
