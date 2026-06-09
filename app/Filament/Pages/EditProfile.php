<?php

namespace App\Filament\Pages;

use App\Filament\Support\PhoneDisplay;
use App\Filament\Support\UserAvatarFields;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\PhoneNumberHelper;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;
use Closure;

class EditProfile extends BaseEditProfile
{
    protected function getRedirectUrl(): ?string
    {
        return Filament::getCurrentPanel()->getProfileUrl();
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
        return Action::make('home')
            ->label('На главную')
            ->color('gray')
            ->icon('heroicon-o-home')
            ->url($this->getHomeUrl());
    }

    protected function getHomeUrl(): string
    {
        return auth()->user()->getFilamentHomeUrl();
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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['avatar']);

        return $data;
    }

    public function persistProfileAvatar(): void
    {
        UserAvatarFields::persistAvatarOnProfile($this);
    }

    public function updated($propertyName): void
    {
        if (! is_string($propertyName) || ! str_starts_with($propertyName, 'data.avatar')) {
            return;
        }

        $this->js('setTimeout(() => $wire.call("persistProfileAvatar"), 500)');
    }

    protected function getAvatarDisplayComponent(): Component
    {
        return UserAvatarFields::displayComponent();
    }

    protected function getAvatarUploadComponent(): Component
    {
        return UserAvatarFields::uploadComponent();
    }

    protected function getAvatarDeleteAction(): Component
    {
        return UserAvatarFields::deleteActionComponent();
    }

    protected function getPhoneFormComponent(): Component
    {
        return PhoneDisplay::textInput(
            TextInput::make('phone')
                ->label('Телефон')
                ->tel()
                ->maxLength(32)
                ->rules([
                    'nullable',
                    Rule::phone()->country([PhoneNumberHelper::DEFAULT_REGION]),
                ])
                ->rule(static function (): Closure {
                    return function (string $attribute, mixed $value, Closure $fail): void {
                        if (! filled($value)) {
                            return;
                        }
                        $e164 = PhoneNumberHelper::normalize($value, [PhoneNumberHelper::DEFAULT_REGION]);
                        if ($e164 === null) {
                            return;
                        }
                        $exists = User::query()
                            ->where('phone', $e164)
                            ->whereKeyNot(auth()->id())
                            ->exists();
                        if ($exists) {
                            $fail('Этот номер телефона уже используется другим пользователем.');
                        }
                    };
                })
                ->columnSpan(['default' => 'full', 'md' => 1]),
        );
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

    protected function getEmailNotificationsToggleComponent(): Component
    {
        $mailEnabled = SystemSetting::mailNotificationsEnabled();

        return Toggle::make('email_notifications_disabled')
            ->label('Отключить рассылку уведомлений на email')
            ->helperText(
                $mailEnabled
                    ? 'Служебные письма из панели (назначение контакта, предупреждения и т.д.) не будут приходить на вашу почту. Уведомления в колокольчике останутся.'
                    : 'Рассылка уведомлений на email отключена администратором в настройках почтового сервера.'
            )
            ->default(false)
            ->inline(false)
            ->disabled(! $mailEnabled)
            ->dehydrated($mailEnabled);
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

                Section::make('Уведомления')
                    ->schema([
                        $this->getEmailNotificationsToggleComponent(),
                    ]),

                Section::make('Безопасность')
                    ->schema([
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getCurrentPasswordFormComponent(),
                    ]),
            ]);
    }
}
