<?php

namespace App\Filament\Livewire;

use App\Filament\Support\DatabaseNotificationActions;
use Filament\Livewire\DatabaseNotifications as BaseDatabaseNotifications;
use Filament\Notifications\Notification;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\On;

class DatabaseNotifications extends BaseDatabaseNotifications
{
    public function getNotification(DatabaseNotification $notification): Notification
    {
        $filamentNotification = parent::getNotification($notification);

        return $filamentNotification->actions(
            DatabaseNotificationActions::forNotification(
                $filamentNotification->getActions(),
                $notification->getKey(),
                $notification->unread(),
            ),
        );
    }

    #[On('markedNotificationAsRead')]
    public function markNotificationAsRead(string $id): void
    {
        if (! $this->isValidNotificationId($id)) {
            return;
        }

        $this->getNotificationsQuery()
            ->whereKey($id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    private function isValidNotificationId(string $id): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) === 1;
    }
}
