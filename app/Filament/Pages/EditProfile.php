<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ContactResource;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    protected function getRedirectUrl(): ?string
    {
        $user = auth()->user();

        if ($user->has_dashboard_access) {
            return Filament::getUrl();
        }

        if ($user->hasRole('leader')) {
            return ContactResource::getUrl('index');
        }

        if ($user->hasRole('manager')) {
            return \App\Filament\Resources\ManagementResource::getUrl('index');
        }

        if ($user->hasRole('superadmin')) {
            return \App\Filament\Resources\UserResource::getUrl('index');
        }

        return Filament::getUrl();
    }

    protected function getNameFormComponent(): Component
    {
        return TextInput::make('name')
            ->label(__('filament-panels::auth/pages/edit-profile.form.name.label'))
            ->required()
            ->maxLength(255)
            ->autofocus();
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('filament-panels::auth/pages/edit-profile.form.email.label'))
            ->email()
            ->required()
            ->maxLength(255)
            ->disabled()
            ->dehydrated(false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }
}
