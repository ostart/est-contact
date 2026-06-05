<?php

namespace Tests\Unit;

use App\Filament\Support\DatabaseNotificationActions;
use Illuminate\Support\Js;
use Tests\TestCase;

class NotificationDatabaseMessageTest extends TestCase
{
    public function test_stored_view_action_uses_url_for_resolve(): void
    {
        $url = '/admin/contacts/1';
        $storedActions = [
            DatabaseNotificationActions::viewUrl('view_contact', DatabaseNotificationActions::VIEW_LABEL, $url),
        ];

        $this->assertSame($url, DatabaseNotificationActions::resolveViewUrl($storedActions));
    }

    public function test_for_notification_embeds_id_in_mark_as_read_handler(): void
    {
        $notificationId = '550e8400-e29b-41d4-a716-446655440000';
        $url = '/admin/contacts/5';
        $storedActions = [
            DatabaseNotificationActions::viewUrl('view_contact', DatabaseNotificationActions::VIEW_LABEL, $url),
            DatabaseNotificationActions::markAsRead(),
        ];

        $actions = DatabaseNotificationActions::forNotification($storedActions, $notificationId, isUnread: true);

        $this->assertCount(2, $actions);
        $this->assertSame(
            DatabaseNotificationActions::dispatchMarkAsReadHandler($notificationId),
            $actions[1]->getAlpineClickHandler(),
        );
        $this->assertSame(
            DatabaseNotificationActions::dispatchMarkAsReadHandler($notificationId).'; window.location.href = '.Js::from($url),
            $actions[0]->getAlpineClickHandler(),
        );
        $this->assertSame(DatabaseNotificationActions::VIEW_LABEL, $actions[0]->getLabel());
    }

    public function test_read_notification_shows_view_only(): void
    {
        $notificationId = '550e8400-e29b-41d4-a716-446655440000';
        $url = '/admin/contacts/5';
        $storedActions = [
            DatabaseNotificationActions::viewUrl('view_contact', DatabaseNotificationActions::VIEW_LABEL, $url),
            DatabaseNotificationActions::markAsRead(),
        ];

        $actions = DatabaseNotificationActions::forNotification($storedActions, $notificationId, isUnread: false);

        $this->assertCount(1, $actions);
        $this->assertSame(
            'window.location.href = '.Js::from($url),
            $actions[0]->getAlpineClickHandler(),
        );
    }

    public function test_dispatch_handler_contains_notification_id(): void
    {
        $notificationId = '550e8400-e29b-41d4-a716-446655440000';

        $handler = DatabaseNotificationActions::dispatchMarkAsReadHandler($notificationId);

        $this->assertStringContainsString(Js::from($notificationId), $handler);
        $this->assertStringContainsString('markedNotificationAsRead', $handler);
    }
}
