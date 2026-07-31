<?php

namespace App\Support;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\User;
use InvalidArgumentException;

class ContactReopenService
{
    /**
     * Статусы, из которых лидер переводит контакт в работу одной кнопкой.
     *
     * @return list<ContactStatus>
     */
    public static function takeToWorkSourceStatuses(): array
    {
        return [
            ContactStatus::NOT_PROCESSED,
            ContactStatus::ASSIGNED,
            ContactStatus::FROZEN,
            ContactStatus::OVERDUE,
            ContactStatus::FAILED,
        ];
    }

    public static function isTakeToWorkSource(ContactStatus $status): bool
    {
        return in_array($status, self::takeToWorkSourceStatuses(), true);
    }

    public static function usesTakeToWorkLabel(ContactStatus $status): bool
    {
        return in_array($status, [ContactStatus::NOT_PROCESSED, ContactStatus::ASSIGNED], true);
    }

    public static function takeToWork(Contact $contact, User $leader): void
    {
        $status = $contact->status instanceof ContactStatus
            ? $contact->status
            : ContactStatus::from((string) $contact->status);

        if (! self::isTakeToWorkSource($status)) {
            throw new InvalidArgumentException(
                "Cannot take contact to work from status [{$status->value}].",
            );
        }

        $contact->update([
            'status' => ContactStatus::IN_PROGRESS,
            'assigned_leader_id' => $leader->id,
        ]);
    }

    public static function reopenFromFailed(Contact $contact, User $leader): void
    {
        self::takeToWork($contact, $leader);
    }
}
