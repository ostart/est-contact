<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ContactResource;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class EditProfile extends BaseEditProfile
{
    protected function getRedirectUrl(): ?string
    {
        return Filament::getCurrentPanel()->getProfileUrl();
    }
    
    protected function afterSave(): void
    {
        $this->redirect(Filament::getCurrentPanel()->getProfileUrl());
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label('Сохранить')
            ->submit('save')
            ->keyBindings(['mod+s']);
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')
            ->label('Отменить')
            ->color('gray')
            ->url($this->getCancelUrl());
    }

    protected function getCancelUrl(): string
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

    protected function getAvatarDisplayComponent(): Component
    {
        return Placeholder::make('current_avatar')
            ->label('Текущее фото')
            ->content(function () {
                $user = auth()->user()->fresh();
                if ($user->avatar) {
                    $url = Storage::disk('public')->url($user->avatar);
                    return new HtmlString(
                        '<img src="' . e($url) . '?t=' . time() . '" alt="Avatar" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid #e5e7eb;">'
                    );
                }
                return 'Фото не загружено';
            });
    }

    protected function getAvatarUploadComponent(): Component
    {
        return FileUpload::make('avatar')
            ->label('Загрузить фото')
            ->image()
            ->disk('public')
            ->directory('avatars')
            ->visibility('public')
            ->maxSize(2048)
            ->imageResizeMode('cover')
            ->imageCropAspectRatio('1:1')
            ->imageResizeTargetWidth('200')
            ->imageResizeTargetHeight('200')
            ->previewable(false)
            ->openable(false)
            ->downloadable(false)
            ->helperText('После загрузки нажмите "Сохранить"')
            ->visible(fn () => !auth()->user()->avatar);
    }

    protected function getAvatarDeleteAction(): Component
    {
        return Actions::make([
            Action::make('delete_avatar')
                ->label('Удалить фото')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Удалить фото?')
                ->modalDescription('Вы уверены, что хотите удалить фотографию профиля?')
                ->modalSubmitActionLabel('Да, удалить')
                ->action(function () {
                    $user = auth()->user();
                    if ($user->avatar) {
                        Storage::disk('public')->delete($user->avatar);
                        $user->update(['avatar' => null]);
                    }
                    $this->redirect(Filament::getCurrentPanel()->getProfileUrl());
                }),
        ])->visible(fn () => (bool) auth()->user()->avatar);
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label('Телефон')
            ->tel()
            ->maxLength(20)
            ->columnSpan(['default' => 'full', 'md' => 1]);
    }

    protected function getAddressFormComponent(): Component
    {
        return Textarea::make('address')
            ->label('Адрес')
            ->rows(3)
            ->maxLength(255)
            ->columnSpanFull();
    }

    protected function getBioFormComponent(): Component
    {
        return Textarea::make('bio')
            ->label('Дополнительная информация')
            ->columnSpanFull()
            ->rows(3)
            ->maxLength(1000);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Фотография профиля')
                    ->schema([
                        $this->getAvatarDisplayComponent(),
                        $this->getAvatarUploadComponent(),
                        $this->getAvatarDeleteAction(),
                    ]),

                Section::make('Основная информация')
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                    ]),

                Section::make('Контактные данные')
                    ->schema([
                        $this->getPhoneFormComponent(),
                        $this->getAddressFormComponent(),
                        $this->getBioFormComponent(),
                    ])
                    ->columns(2),

                Section::make('Безопасность')
                    ->schema([
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getCurrentPasswordFormComponent(),
                    ]),
            ]);
    }
}
