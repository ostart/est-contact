<?php

namespace App\Observers;

use App\Models\Contact;
use App\Models\ContactStatusHistory;
use App\Notifications\ContactAssignedNotification;
use Carbon\Carbon;

class ContactObserver
{
    /**
     * Handle the Contact "created" event.
     */
    public function created(Contact $contact): void
    {
        // Логируем начальный статус (created_at явно в UTC)
        ContactStatusHistory::create([
            'contact_id' => $contact->id,
            'user_id' => auth()->id() ?? $contact->created_by,
            'old_status' => null,
            'new_status' => $contact->status->value,
            'created_at' => Carbon::now('UTC'),
        ]);
    }

    /**
     * Handle the Contact "updated" event.
     */
    public function updated(Contact $contact): void
    {
        // Проверяем изменение статуса (created_at явно в UTC)
        if ($contact->isDirty('status')) {
            ContactStatusHistory::create([
                'contact_id' => $contact->id,
                'user_id' => auth()->id(), // null при смене статуса из консоли (например, contacts:check-overdue)
                'old_status' => $contact->getOriginal('status'),
                'new_status' => $contact->status->value,
                'created_at' => Carbon::now('UTC'),
            ]);
        }

        // Проверяем назначение лидера (wasChanged — после save; isDirty в updated уже сброшен)
        if ($contact->wasChanged('assigned_leader_id') && $contact->assigned_leader_id) {
            $leader = $contact->assignedLeader;
            if ($leader) {
                $leader->notify(new ContactAssignedNotification($contact));
            }
        }
    }

    /**
     * Handle the Contact "deleted" event.
     */
    public function deleted(Contact $contact): void
    {
        //
    }

    /**
     * Handle the Contact "restored" event.
     */
    public function restored(Contact $contact): void
    {
        //
    }

    /**
     * Handle the Contact "force deleted" event.
     */
    public function forceDeleted(Contact $contact): void
    {
        //
    }
}

