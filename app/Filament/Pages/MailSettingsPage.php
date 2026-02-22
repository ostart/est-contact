<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Настройки почтового сервера для отправки уведомлений (назначение контакта лидеру).
 * Документация Timeweb: https://timeweb.com/ru/docs/pochta/
 *
 * @property-read Schema $form
 */
class MailSettingsPage extends Page
{
    use CanUseDatabaseTransactions;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Почтовый сервер';

    protected static ?string $title = 'Настройки почтового сервера';

    protected static string | \UnitEnum | null $navigationGroup = 'Настройки';

    protected static ?int $navigationSort = 1;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public static function getSlug(?Panel $panel = null): string
    {
        return 'settings/mail';
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('superadmin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->fillForm();
    }

    protected function fillForm(): void
    {
        $data = [
            'mail_notifications_enabled' => (bool) filter_var(SystemSetting::get('mail_notifications_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
            'mail_host' => SystemSetting::get('mail_host', ''),
            'mail_port' => SystemSetting::get('mail_port', '465'),
            'mail_encryption' => SystemSetting::get('mail_encryption', 'ssl'),
            'mail_username' => SystemSetting::get('mail_username', ''),
            'mail_password' => SystemSetting::get('mail_password', ''),
            'mail_from_address' => SystemSetting::get('mail_from_address', ''),
            'mail_from_name' => SystemSetting::get('mail_from_name', 'Есть Контакт'),
        ];

        $this->form->fill($data);
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();

            $data = $this->form->getState();

            foreach ($this->mailKeys() as $key) {
                $value = Arr::get($data, $key);
                if ($key === 'mail_password' && blank($value)) {
                    continue; // не перезаписываем пароль пустым
                }
                if ($key === 'mail_notifications_enabled') {
                    SystemSetting::set($key, $value ? '1' : '0');
                    continue;
                }
                SystemSetting::set($key, (string) $value);
            }

            $this->commitDatabaseTransaction();

            Notification::make()
                ->success()
                ->title('Настройки почты сохранены')
                ->send();
        } catch (Halt $exception) {
            $this->rollBackDatabaseTransaction();
            return;
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();
            throw $exception;
        }
    }

    /**
     * @return array<string>
     */
    private function mailKeys(): array
    {
        return [
            'mail_notifications_enabled',
            'mail_host',
            'mail_port',
            'mail_encryption',
            'mail_username',
            'mail_password',
            'mail_from_address',
            'mail_from_name',
        ];
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Рассылка')
                    ->description('Включите отправку email-уведомлений лидерам при назначении контакта на обработку.')
                    ->schema([
                        Toggle::make('mail_notifications_enabled')
                            ->label('Включить рассылку уведомлений по email')
                            ->default(false)
                            ->inline(false),
                    ]),

                Section::make('SMTP-сервер')
                    ->description('Используется для отправки уведомлений лидерам при назначении контакта. Для Timeweb: smtp.timeweb.ru, порт 465 (SSL) или 25/2525 (STARTTLS).')
                    ->schema([
                        TextInput::make('mail_host')
                            ->label('Хост')
                            ->placeholder('smtp.timeweb.ru')
                            ->maxLength(255),
                        TextInput::make('mail_port')
                            ->label('Порт')
                            ->placeholder('465')
                            ->numeric()
                            ->maxLength(5),
                        Select::make('mail_encryption')
                            ->label('Шифрование')
                            ->options([
                                'ssl' => 'SSL (порт 465)',
                                'tls' => 'TLS / STARTTLS (порт 25, 2525)',
                                '' => 'Без шифрования',
                            ]),
                        TextInput::make('mail_username')
                            ->label('Логин (адрес почтового ящика)')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('mail_password')
                            ->label('Пароль')
                            ->password()
                            ->revealable()
                            ->placeholder('Оставьте пустым, чтобы не менять')
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Отправитель')
                    ->schema([
                        TextInput::make('mail_from_address')
                            ->label('Email отправителя')
                            ->email()
                            ->placeholder('noreply@ваш-домен.ru')
                            ->maxLength(255),
                        TextInput::make('mail_from_name')
                            ->label('Имя отправителя')
                            ->placeholder('Есть Контакт')
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Сохранить')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make($this->getFormActions())->key('form-actions'),
                    ]),
            ]);
    }
}
