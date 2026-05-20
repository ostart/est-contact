<?php

namespace App\Filament\Support;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Models\UserWarning;
use App\Support\UserModerationActivity;
use App\Notifications\UserBannedNotification;
use App\Notifications\UserUnbannedNotification;
use App\Notifications\UserWarningNotification;
use App\Notifications\UserWarningsClearedNotification;
use Filament\Actions\Action;
use Filament\Forms\Components;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class UserActionsSection
{
    /**
     * @return array<Action>
     */
    public static function editFormActions(): array
    {
        return [
            static::banAction(),
            static::unbanAction(),
            static::warnAction(),
            static::clearWarningsAction(),
        ];
    }

    public static function banAction(): Action
    {
        return Action::make('ban_user')
            ->label('Заблокировать')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (User $record): bool => ! $record->is_banned && ! $record->isSuperAdmin())
            ->requiresConfirmation()
            ->modalHeading('Заблокировать пользователя')
            ->modalDescription(fn (User $record): string => "Вы уверены, что хотите заблокировать пользователя {$record->name}?")
            ->form([
                Components\Textarea::make('ban_reason')
                    ->label('Причина блокировки')
                    ->maxLength(1000)
                    ->rows(2)
                    ->placeholder('Укажите причину блокировки (необязательно)...'),
            ])
            ->action(function (User $record, array $data, EditRecord $livewire): void {
                static::banUser($record, $data['ban_reason'] ?? null);
                $livewire->redirect(UserResource::getUrl('edit', ['record' => $record]));
            });
    }

    public static function unbanAction(): Action
    {
        return Action::make('unban_user')
            ->label('Разблокировать')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (User $record): bool => $record->is_banned)
            ->requiresConfirmation()
            ->modalHeading('Разблокировать пользователя')
            ->modalDescription(fn (User $record): string => "Вы уверены, что хотите разблокировать пользователя {$record->name}?")
            ->action(function (User $record, EditRecord $livewire): void {
                static::unbanUser($record);
                $livewire->redirect(UserResource::getUrl('edit', ['record' => $record]));
            });
    }

    public static function warnAction(): Action
    {
        return Action::make('warn_user')
            ->label('Отправить предупреждение')
            ->icon('heroicon-o-exclamation-triangle')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Отправить предупреждение')
            ->modalDescription(fn (User $record): string => "Пользователь {$record->name} получит уведомление в колокольчик и на email (если включено в настройках).")
            ->form([
                Components\Textarea::make('message')
                    ->label('Текст предупреждения')
                    ->required()
                    ->maxLength(1000)
                    ->rows(3)
                    ->placeholder('Введите текст предупреждения для пользователя...'),
            ])
            ->action(function (User $record, array $data, EditRecord $livewire): void {
                static::sendWarning($record, $data['message']);
                $livewire->redirect(UserResource::getUrl('edit', ['record' => $record]));
            });
    }

    public static function clearWarningsAction(): Action
    {
        return Action::make('clear_warnings')
            ->label('Очистить все предупреждения и баны')
            ->icon('heroicon-o-trash')
            ->color('gray')
            ->visible(fn (User $record): bool => $record->warnings()->exists()
                || $record->is_banned
                || UserModerationActivity::banLogQueryForUser($record)->exists())
            ->requiresConfirmation()
            ->modalHeading('Очистить все предупреждения и баны')
            ->modalDescription(fn (User $record): string => "Вы уверены, что хотите удалить все предупреждения и снять блокировку пользователя {$record->name}? Пользователь получит уведомление.")
            ->action(function (User $record, EditRecord $livewire): void {
                static::clearWarningsAndBan($record);
                $livewire->redirect(UserResource::getUrl('edit', ['record' => $record]));
            });
    }

    public static function banUser(User $user, ?string $reason): void
    {
        if ($user->isSuperAdmin()) {
            Notification::make()
                ->danger()
                ->title('Действие недоступно')
                ->body('Суперадминистратора нельзя заблокировать.')
                ->send();

            return;
        }

        $user->update([
            'is_banned' => true,
            'ban_reason' => $reason,
            'banned_at' => now(),
        ]);

        $user->notify(new UserBannedNotification($reason));

        Notification::make()
            ->success()
            ->title('Пользователь заблокирован')
            ->body("Пользователь {$user->name} был заблокирован. Уведомление отправлено.")
            ->send();
    }

    public static function unbanUser(User $user): void
    {
        $user->update([
            'is_banned' => false,
            'ban_reason' => null,
            'banned_at' => null,
        ]);

        $user->notify(new UserUnbannedNotification());

        Notification::make()
            ->success()
            ->title('Пользователь разблокирован')
            ->body("Пользователь {$user->name} был разблокирован. Уведомление отправлено.")
            ->send();
    }

    public static function sendWarning(User $user, string $message): void
    {
        UserWarning::create([
            'user_id' => $user->id,
            'warned_by' => auth()->id(),
            'message' => $message,
        ]);

        $user->notify(new UserWarningNotification($message));

        Notification::make()
            ->success()
            ->title('Предупреждение отправлено')
            ->body("Пользователь {$user->name} получит уведомление в колокольчик и на email (если включено в настройках).")
            ->send();
    }

    public static function clearWarningsAndBan(User $user): void
    {
        $hadWarnings = $user->warnings()->exists();
        $warningIds = $user->warnings()->pluck('id');
        $wasBanned = (bool) $user->is_banned && ! $user->isSuperAdmin();
        $hadBanHistory = UserModerationActivity::banLogQueryForUser($user)->exists();

        if ($warningIds->isNotEmpty()) {
            UserModerationActivity::deleteWarningLogs($warningIds);
        }

        $user->warnings()->delete();

        if ($wasBanned) {
            $user->update([
                'is_banned' => false,
                'ban_reason' => null,
                'banned_at' => null,
            ]);
        }

        $clearedBanHistory = UserModerationActivity::deleteBanLogsForUser($user) > 0;

        $hadBanChanges = $wasBanned || $clearedBanHistory || $hadBanHistory;

        if ($hadWarnings || $hadBanChanges) {
            $user->notify(new UserWarningsClearedNotification($hadWarnings, $hadBanChanges));
        }

        Notification::make()
            ->success()
            ->title('Предупреждения и баны очищены')
            ->body(match (true) {
                $hadWarnings && $hadBanChanges => "Все предупреждения удалены, блокировка и история банов пользователя {$user->name} сняты. Уведомление отправлено.",
                $hadWarnings => "Все предупреждения пользователя {$user->name} удалены. Уведомление отправлено.",
                $hadBanChanges => "Блокировка и история банов пользователя {$user->name} сняты. Уведомление отправлено.",
                default => "У пользователя {$user->name} не было предупреждений и банов.",
            })
            ->send();
    }
}
