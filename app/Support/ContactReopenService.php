<?php

namespace App\Support;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\User;

class ContactReopenService
{
    public static function reopenFromFailed(Contact $contact, User $leader): void
    {
        $contact->update([
            'status' => ContactStatus::IN_PROGRESS,
            'assigned_leader_id' => $leader->id,
        ]);
    }
}
