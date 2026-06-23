<?php

namespace App\Observers;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\ContactStatusHistory;
use App\Notifications\ContactAssignedNotification;
use App\Notifications\ContactOverdueNotification;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

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

        // При создании с назначенным лидером updated не вызывается — уведомляем здесь.
        if ($contact->assigned_leader_id) {
            $this->notifyAssignedLeader($contact);
        }
    }

    public function saving(Contact $contact): void
    {
        if ($contact->isDirty('status')) {
            $newStatus = $contact->status instanceof ContactStatus
                ? $contact->status
                : ContactStatus::from($contact->status);

            if ($newStatus !== ContactStatus::FROZEN) {
                $contact->frozen_until = null;
            } elseif ($contact->frozen_until === null) {
                throw ValidationException::withMessages([
                    'frozen_until' => 'Укажите дату разморозки.',
                ]);
            } else {
                $frozenUntil = Carbon::parse($contact->frozen_until)->utc();

                if ($frozenUntil->lte(now('UTC'))) {
                    throw ValidationException::withMessages([
                        'frozen_until' => 'Дата разморозки должна быть в будущем.',
                    ]);
                }
            }
        }

        $contact->applyProcessingActivityOnSave();
    }

    /**
     * Handle the Contact "updated" event.
     */
    public function updated(Contact $contact): void
    {
        // Проверяем изменение статуса (created_at явно в UTC)
        if ($contact->wasChanged('status')) {
            $oldStatusValue = $contact->getRawOriginal('status');
            $oldStatus = is_string($oldStatusValue)
                ? ContactStatus::tryFrom($oldStatusValue)
                : null;
            $newStatus = $contact->status instanceof ContactStatus
                ? $contact->status
                : ContactStatus::from($contact->status);

            if ($oldStatus && ! $oldStatus->canTransitionTo(
                $newStatus,
                forManager: auth()->user()?->hasAnyRole(['manager', 'administrator', 'superadmin']) ?? false,
                system: app()->runningInConsole(),
            )) {
                throw ValidationException::withMessages([
                    'status' => 'Недопустимый переход статуса.',
                ]);
            }

            ContactStatusHistory::create([
                'contact_id' => $contact->id,
                'user_id' => auth()->id(), // null при смене статуса из консоли (contacts:check-overdue)
                'old_status' => $oldStatusValue,
                'new_status' => $newStatus->value,
                'created_at' => Carbon::now('UTC'),
            ]);
        }

        // Проверяем назначение лидера (wasChanged — после save; isDirty в updated уже сброшен)
        if ($contact->wasChanged('assigned_leader_id') && $contact->assigned_leader_id) {
            $this->notifyAssignedLeader($contact);
        }

        if ($contact->wasChanged('status') && $contact->status === ContactStatus::OVERDUE && $contact->assigned_leader_id) {
            $leader = $contact->assignedLeader;
            if ($leader) {
                $leader->notify(new ContactOverdueNotification($contact));
            }
        }
    }

    protected function notifyAssignedLeader(Contact $contact): void
    {
        $leader = $contact->assignedLeader;

        if ($leader) {
            $leader->notify(new ContactAssignedNotification($contact));
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

