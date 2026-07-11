<?php

namespace App\Filament\Support;

use App\Enums\ContactStatus;
use App\Models\Contact;
use Carbon\Carbon;
use DateTimeInterface;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ContactFreezeFields
{
    public static function statusIsFrozen(Get $get): bool
    {
        return $get('status') === ContactStatus::FROZEN->value;
    }

    public static function resolveStatusFromRecord(?Model $record): ?ContactStatus
    {
        if (! $record instanceof Contact) {
            return null;
        }

        return $record->status instanceof ContactStatus
            ? $record->status
            : ContactStatus::tryFrom((string) $record->status);
    }

    public static function isTransitionToFrozen(Get $get, ?ContactStatus $currentStatus): bool
    {
        return self::statusIsFrozen($get) && $currentStatus !== ContactStatus::FROZEN;
    }

    /**
     * @return array<int, DatePicker|Textarea>
     */
    public static function schema(): array
    {
        $showFreezeFields = function (Get $get, $livewire): bool {
            return self::isTransitionToFrozen(
                $get,
                self::resolveStatusFromRecord($livewire->getRecord()),
            );
        };

        return [
            DatePicker::make('freeze_date')
                ->label('Дата разморозки')
                ->timezone('Europe/Moscow')
                ->minDate(now('Europe/Moscow')->addDay()->startOfDay())
                ->default(fn () => now('Europe/Moscow')->addDays(7)->toDateString())
                ->helperText('Разморозка в 00:00 по московскому времени в выбранный день')
                ->required($showFreezeFields)
                ->visible($showFreezeFields)
                ->dehydrated($showFreezeFields),

            Textarea::make('freeze_reason')
                ->label('Причина заморозки (необязательно)')
                ->rows(2)
                ->maxLength(2000)
                ->visible($showFreezeFields)
                ->dehydrated($showFreezeFields),
        ];
    }

    public static function formatFrozenUntilDisplay(mixed $value): ?string
    {
        $frozenUntil = self::resolveFrozenUntilValue($value);

        return $frozenUntil?->timezone('Europe/Moscow')->format('d.m.Y');
    }

    public static function resolveFrozenUntilValue(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->utc();
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value, 'UTC');
        }

        if (is_string($value) && preg_match('/^\d+UTC/', $value)) {
            return Carbon::createFromTimestamp((int) $value, 'UTC');
        }

        return Carbon::parse($value, 'UTC');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function resolveFrozenUntil(array $data): ?Carbon
    {
        if (empty($data['freeze_date'])) {
            return null;
        }

        return Carbon::parse($data['freeze_date'], 'Europe/Moscow')
            ->startOfDay()
            ->utc();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function assertFrozenUntilValid(?Carbon $frozenUntil): void
    {
        if ($frozenUntil === null) {
            throw ValidationException::withMessages([
                'freeze_date' => 'Укажите дату разморозки.',
            ]);
        }

        if ($frozenUntil->lte(now('UTC'))) {
            throw ValidationException::withMessages([
                'freeze_date' => 'Дата разморозки должна быть в будущем.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function applyFreeze(Contact $contact, array $data): void
    {
        $current = $contact->status instanceof ContactStatus
            ? $contact->status
            : ContactStatus::from($contact->status);

        if ($current === ContactStatus::FROZEN) {
            return;
        }

        $frozenUntil = self::resolveFrozenUntil($data);
        self::assertFrozenUntilValid($frozenUntil);

        $contact->update([
            'status' => ContactStatus::FROZEN->value,
            'frozen_until' => $frozenUntil,
        ]);

        $reason = trim((string) ($data['freeze_reason'] ?? ''));
        if ($reason !== '') {
            $contact->comments()->create([
                'comment' => $reason,
                'user_id' => auth()->id(),
                'created_at' => Carbon::now('UTC'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergeIntoFormData(array $data, ?ContactStatus $currentStatus = null): array
    {
        $targetStatus = $data['status'] ?? null;
        $isFreezing = $targetStatus === ContactStatus::FROZEN->value
            && $currentStatus !== ContactStatus::FROZEN;

        if ($isFreezing) {
            $frozenUntil = self::resolveFrozenUntil($data);
            self::assertFrozenUntilValid($frozenUntil);
            $data['frozen_until'] = $frozenUntil;
        } elseif ($targetStatus !== ContactStatus::FROZEN->value) {
            $data['frozen_until'] = null;
        }

        unset($data['freeze_date'], $data['freeze_reason']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function splitFrozenUntilForForm(array $data): array
    {
        $frozenUntil = self::resolveFrozenUntilValue($data['frozen_until'] ?? null);
        if ($frozenUntil !== null) {
            $data['freeze_date'] = $frozenUntil
                ->timezone('Europe/Moscow')
                ->toDateString();
        }

        return $data;
    }
}
