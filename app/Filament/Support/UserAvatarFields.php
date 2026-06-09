<?php

namespace App\Filament\Support;

use App\Filament\Pages\EditProfile;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class UserAvatarFields
{
    public static function avatarHtml(string $url, string $alt): HtmlString
    {
        return new HtmlString(
            '<div style="width: 100%;">'
            . '<img src="' . e($url) . '" alt="' . e($alt) . '" '
            . 'style="width: 100%; max-height: 28rem; object-fit: contain; display: block; border: 1px solid #e5e7eb; border-radius: 0.375rem;">'
            . '</div>'
        );
    }

    public static function displayComponent(): Component
    {
        return Placeholder::make('current_avatar')
            ->label('Текущее фото')
            ->content(function (): HtmlString|string {
                $user = auth()->user();

                if (! filled($user?->avatar)) {
                    return 'Фото не загружено';
                }

                $url = Storage::disk('public')->url($user->avatar) . '?t=' . time();

                return static::avatarHtml($url, $user->name);
            })
            ->visible(fn (): bool => filled(auth()->user()?->avatar));
    }

    public static function uploadComponent(): Component
    {
        return FileUpload::make('avatar')
            ->label('Загрузить фото')
            ->image()
            ->disk('public')
            ->directory('avatars')
            ->visibility('public')
            ->maxSize(5120)
            ->previewable(false)
            ->openable(false)
            ->downloadable(false)
            ->helperText('Изображение до 5 МБ. Сохраняется автоматически после загрузки.')
            ->visible(fn (): bool => ! filled(auth()->user()?->avatar));
    }

    public static function deleteActionComponent(): Component
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
                ->action(function (EditProfile $livewire): void {
                    static::deleteAvatar(auth()->user());
                    static::refreshAvatarFormAfterChange($livewire);
                }),
        ])->visible(fn (): bool => filled(auth()->user()?->avatar));
    }

    public static function assignAvatarToUser(User $user, string $path): void
    {
        if ($user->avatar === $path) {
            return;
        }

        if (filled($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->forceFill(['avatar' => $path])->save();
    }

    public static function deleteAvatar(User $user): void
    {
        if (! filled($user->avatar)) {
            return;
        }

        Storage::disk('public')->delete($user->avatar);
        $user->update(['avatar' => null]);
    }

    public static function persistAvatarOnProfile(EditProfile $livewire): void
    {
        $upload = static::extractTemporaryUpload($livewire->data['avatar'] ?? null);

        if (! $upload instanceof TemporaryUploadedFile) {
            return;
        }

        try {
            if (! $upload->exists()) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $extension = $upload->getClientOriginalExtension() ?: $upload->extension() ?: 'jpg';

        $path = $upload->storeAs(
            'avatars',
            Str::ulid() . '.' . $extension,
            'public',
        );

        if (blank($path) || ! Storage::disk('public')->exists($path)) {
            return;
        }

        Storage::disk('public')->setVisibility($path, 'public');

        static::assignAvatarToUser(auth()->user(), $path);

        $upload->delete();

        static::refreshAvatarFormAfterChange($livewire);

        if (! filled(auth()->user()->fresh()->avatar)) {
            return;
        }

        Notification::make()
            ->title('Фото сохранено')
            ->success()
            ->send();
    }

    public static function refreshAvatarFormAfterChange(EditProfile $livewire): void
    {
        auth()->user()?->refresh();

        if (is_array($livewire->data ?? null)) {
            unset($livewire->data['avatar']);
        }
    }

    protected static function extractTemporaryUpload(mixed $state): ?TemporaryUploadedFile
    {
        if ($state instanceof TemporaryUploadedFile) {
            return $state;
        }

        if (! is_array($state)) {
            return null;
        }

        foreach ($state as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                return $file;
            }
        }

        return null;
    }
}
