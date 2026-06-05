<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Illuminate\Support\Js;

/**
 * Единообразные действия для уведомлений в колокольчике Filament.
 */
final class DatabaseNotificationActions
{
    public const VIEW_LABEL = 'Просмотр';

    /**
     * Действия для сохранения в БД (обработчики подставляются при отображении).
     */
    public static function markAsRead(): Action
    {
        return Action::make('mark_as_read')
            ->label('Прочитано')
            ->button()
            ->markAsRead();
    }

    public static function viewUrl(string $name, string $label, string $url): Action
    {
        return Action::make($name)
            ->label($label)
            ->button()
            ->url($url);
    }

    /**
     * @param  array<int, Action>  $storedActions
     * @return array<int, Action>
     */
    public static function forNotification(array $storedActions, string $notificationId, bool $isUnread): array
    {
        $viewUrl = self::resolveViewUrl($storedActions);
        $actions = [];

        if ($viewUrl !== null) {
            $viewName = self::resolveViewActionName($storedActions);

            $actions[] = $isUnread
                ? self::viewWhenUnread($viewName, self::VIEW_LABEL, $viewUrl, $notificationId)
                : self::viewWhenRead($viewName, self::VIEW_LABEL, $viewUrl);
        }

        if ($isUnread) {
            $actions[] = self::markAsReadFor($notificationId);
        }

        return $actions;
    }

    public static function markAsReadFor(string $notificationId): Action
    {
        return Action::make('mark_as_read')
            ->label('Прочитано')
            ->button()
            ->alpineClickHandler(self::dispatchMarkAsReadHandler($notificationId));
    }

    public static function viewWhenUnread(string $name, string $label, string $url, string $notificationId): Action
    {
        return Action::make($name)
            ->label($label)
            ->button()
            ->alpineClickHandler(
                self::dispatchMarkAsReadHandler($notificationId).'; window.location.href = '.Js::from($url),
            );
    }

    public static function viewWhenRead(string $name, string $label, string $url): Action
    {
        return Action::make($name)
            ->label($label)
            ->button()
            ->alpineClickHandler('window.location.href = '.Js::from($url));
    }

    public static function dispatchMarkAsReadHandler(string $notificationId): string
    {
        return 'window.dispatchEvent(new CustomEvent(\'markedNotificationAsRead\', { detail: { id: '.Js::from($notificationId).' } }))';
    }

    /**
     * @param  array<int, Action>  $actions
     */
    public static function resolveViewUrl(array $actions): ?string
    {
        foreach ($actions as $action) {
            if ($url = $action->getUrl()) {
                return $url;
            }

            $handler = $action->getCustomAlpineClickHandler();

            if (! is_string($handler) || ! str_contains($handler, 'window.location.href')) {
                continue;
            }

            if (preg_match('/window\.location\.href\s*=\s*(.+)\s*$/', $handler, $matches)) {
                $url = self::parseJsStringLiteral(trim($matches[1]));

                if ($url !== null) {
                    return $url;
                }
            }
        }

        return null;
    }

    private static function parseJsStringLiteral(string $literal): ?string
    {
        if (str_starts_with($literal, "'") && str_ends_with($literal, "'")) {
            return stripcslashes(substr($literal, 1, -1));
        }

        if (str_starts_with($literal, '"') && str_ends_with($literal, '"')) {
            $decoded = json_decode($literal, true);

            return is_string($decoded) ? $decoded : null;
        }

        return null;
    }

    /**
     * @param  array<int, Action>  $actions
     */
    private static function resolveViewActionName(array $actions): string
    {
        foreach ($actions as $action) {
            $handler = $action->getCustomAlpineClickHandler();
            $url = $action->getUrl();

            if ($url || (is_string($handler) && str_contains($handler, 'window.location.href'))) {
                return $action->getName();
            }
        }

        return 'view_contact';
    }
}
