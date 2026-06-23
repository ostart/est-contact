<?php

namespace App\Observers;

use App\Enums\ContactStatus;
use App\Models\ContactComment;
use App\Models\ContactStatusHistory;
use Carbon\Carbon;

class ContactCommentObserver
{
    public function created(ContactComment $comment): void
    {
        $contact = $comment->contact;

        if ($contact === null || $contact->assigned_leader_id === null) {
            return;
        }

        if ($comment->user_id !== $contact->assigned_leader_id) {
            return;
        }

        $activityAt = Carbon::parse($comment->created_at)->utc();
        $contact->touchProcessingActivity($activityAt);

        if ($contact->status === ContactStatus::OVERDUE) {
            $oldStatus = $contact->status->value;
            $contact->status = $contact->statusBeforeOverdue();
            $contact->saveQuietly();

            ContactStatusHistory::create([
                'contact_id' => $contact->id,
                'user_id' => $comment->user_id,
                'old_status' => $oldStatus,
                'new_status' => $contact->status->value,
                'created_at' => $activityAt,
            ]);

            return;
        }

        $contact->saveQuietly();
    }
}
