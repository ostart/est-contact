<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Support\Facades\FilamentView;
use Illuminate\Database\Eloquent\Model;

class Register extends BaseRegister
{
    public function mount(): void
    {
        parent::mount();

        if (request()->has('locale')) {
            session(['locale' => request()->get('locale')]);
        }
    }

    protected function handleRegistration(array $data): Model
    {
        $user = User::create($data);
        $user->assignRole('leader');

        return $user;
    }

    public function register(): ?RegistrationResponse
    {
        parent::register();

        $this->redirect(route('approval.pending'), navigate: FilamentView::hasSpaMode(route('approval.pending')));

        return null;
    }
}
