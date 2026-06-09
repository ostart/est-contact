<?php

namespace App\Filament\Support;

use App\Models\Contact;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Infolists\Components;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\ImageColumn;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ContactPhotoFields
{
    public static function defaultImageUrl(Contact $contact, int $size = 32): string
    {
        $name = trim($contact->full_name ?? '') ?: '?';

        return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&size=' . $size . '&background=random';
    }

    public static function photoUrl(?Contact $contact): ?string
    {
        if (! filled($contact?->photo)) {
            return null;
        }

        if (! Storage::disk('public')->exists($contact->photo)) {
            return null;
        }

        return Storage::disk('public')->url($contact->photo);
    }

    public static function tableImageUrl(Contact $contact): string
    {
        return static::photoUrl($contact) ?? static::defaultImageUrl($contact);
    }

    public static function sectionPhotoHtml(string $url, string $alt): HtmlString
    {
        return new HtmlString(
            '<div style="width: 100%;">'
            . '<img src="' . e($url) . '" alt="' . e($alt) . '" '
            . 'style="width: 100%; max-height: 28rem; object-fit: contain; display: block; border: 1px solid #e5e7eb; border-radius: 0.375rem;">'
            . '</div>'
        );
    }

    public static function formSection(): SchemaComponents\Section
    {
        return SchemaComponents\Section::make('Фотография')
            ->schema([
                Placeholder::make('pending_contact_photo')
                    ->label('Загруженное фото')
                    ->content(function (Get $get): HtmlString|string {
                        $url = static::temporaryUploadPreviewUrl($get('photo'));

                        if (blank($url)) {
                            return 'Фото не загружено';
                        }

                        return static::sectionPhotoHtml($url, 'Фото контакта');
                    })
                    ->visible(fn (Get $get, string $operation): bool => $operation === 'create'
                        && static::hasTemporaryUpload($get('photo'))),

                Placeholder::make('current_contact_photo')
                    ->label('Текущее фото')
                    ->content(function (EditRecord $livewire): HtmlString|string {
                        $record = $livewire->getRecord();

                        if (! filled($record->photo)) {
                            return 'Фото не загружено';
                        }

                        $url = Storage::disk('public')->url($record->photo) . '?t=' . time();

                        return static::sectionPhotoHtml($url, 'Фото контакта');
                    })
                    ->visible(fn ($livewire, string $operation): bool => $operation === 'edit'
                        && $livewire instanceof EditRecord
                        && filled($livewire->getRecord()->photo)),

                FileUpload::make('photo')
                    ->label('Загрузить фото')
                    ->image()
                    ->disk('public')
                    ->directory('contact-photos')
                    ->visibility('public')
                    ->maxSize(5120)
                    ->previewable(false)
                    ->openable(false)
                    ->downloadable(false)
                    ->helperText(fn (string $operation): string => $operation === 'edit'
                        ? 'Изображение до 5 МБ. Сохраняется автоматически после загрузки.'
                        : 'Изображение до 5 МБ. Будет сохранено при нажатии «Сохранить».')
                    ->visible(fn ($livewire, string $operation): bool => $operation === 'create'
                        || ($operation === 'edit'
                            && $livewire instanceof EditRecord
                            && ! filled($livewire->getRecord()->photo)))
                    ->hiddenJs(fn (string $operation): ?string => $operation !== 'create' ? null : <<<'JS'
                        (() => {
                            const photo = $get('photo');

                            if (photo === null || photo === undefined || photo === '') {
                                return false;
                            }

                            if (typeof photo === 'object') {
                                return Object.keys(photo).length > 0;
                            }

                            return true;
                        })()
                    JS),

                Actions::make([
                    Action::make('delete_contact_photo')
                        ->label('Удалить фото')
                        ->color('danger')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Удалить фото?')
                        ->modalDescription('Вы уверены, что хотите удалить фотографию контакта?')
                        ->modalSubmitActionLabel('Да, удалить')
                        ->action(function ($livewire, string $operation): void {
                            if ($operation === 'create') {
                                static::clearPendingPhotoFromForm($livewire);

                                return;
                            }

                            if ($livewire instanceof EditRecord) {
                                static::deletePhoto($livewire->getRecord());
                                static::refreshPhotoFormAfterChange($livewire);
                            }
                        }),
                ])->visible(fn (Get $get, $livewire, string $operation): bool => ($operation === 'create'
                    && static::hasTemporaryUpload($get('photo')))
                    || ($operation === 'edit'
                        && $livewire instanceof EditRecord
                        && filled($livewire->getRecord()->photo))),
            ]);
    }

    public static function infolistSection(): SchemaComponents\Section
    {
        return SchemaComponents\Section::make('Фотография')
            ->schema([
                Components\TextEntry::make('photo')
                    ->hiddenLabel()
                    ->formatStateUsing(function (?string $state, Contact $record): HtmlString {
                        $url = filled($state)
                            ? Storage::disk('public')->url($state)
                            : static::defaultImageUrl($record, 400);

                        return static::sectionPhotoHtml($url, $record->full_name);
                    })
                    ->html(),
            ]);
    }

    public static function tableColumn(): ImageColumn
    {
        return ImageColumn::make('photo')
            ->label("\u{200B}")
            ->toggleable(false)
            ->circular()
            ->size(32)
            ->getStateUsing(fn (Contact $record): string => static::tableImageUrl($record));
    }

    public static function assignPhotoToContact(Contact $contact, string $path): void
    {
        if ($contact->photo !== $path) {
            $contact->forceFill(['photo' => $path])->save();
        }
    }

    public static function deletePhoto(Contact $contact): void
    {
        if ($contact->photo) {
            Storage::disk('public')->delete($contact->photo);
            $contact->update(['photo' => null]);
        }
    }

    public static function persistPhotoOnEdit(EditRecord $livewire): void
    {
        $upload = static::extractTemporaryUpload($livewire->data['photo'] ?? null);

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
            'contact-photos',
            Str::ulid() . '.' . $extension,
            'public',
        );

        if (blank($path) || ! Storage::disk('public')->exists($path)) {
            return;
        }

        Storage::disk('public')->setVisibility($path, 'public');

        static::assignPhotoToContact($livewire->getRecord(), $path);

        $upload->delete();

        static::refreshPhotoFormAfterChange($livewire);

        if (! filled($livewire->getRecord()->photo)) {
            return;
        }

        Notification::make()
            ->title('Фото сохранено')
            ->success()
            ->send();
    }

    public static function refreshPhotoFormAfterChange(EditRecord $livewire): void
    {
        $livewire->record = $livewire->getRecord()->fresh();

        if (is_array($livewire->data ?? null)) {
            unset($livewire->data['photo']);
        }
    }

    public static function hasTemporaryUpload(mixed $state): bool
    {
        return static::extractTemporaryUpload($state) instanceof TemporaryUploadedFile;
    }

    public static function temporaryUploadPreviewUrl(mixed $state): ?string
    {
        $upload = static::extractTemporaryUpload($state);

        if (! $upload instanceof TemporaryUploadedFile) {
            return null;
        }

        try {
            if (! $upload->exists()) {
                return null;
            }

            return $upload->temporaryUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function clearPendingPhotoFromForm(object $livewire): void
    {
        $upload = static::extractTemporaryUpload($livewire->data['photo'] ?? null);

        if ($upload instanceof TemporaryUploadedFile) {
            try {
                $upload->delete();
            } catch (\Throwable) {
            }
        }

        if (is_array($livewire->data ?? null)) {
            unset($livewire->data['photo']);
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
