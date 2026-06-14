<?php

namespace App\Support\Dashboard;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\ContactStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ContactStatusHistoryBackfillService
{
    public function backfill(?callable $onProgress = null): int
    {
        $table = (string) config('activitylog.table_name');
        $added = 0;

        $activities = DB::table($table)
            ->where('subject_type', Contact::class)
            ->whereIn('event', ['created', 'updated'])
            ->whereIn('subject_id', Contact::query()->select('id'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->lazy();

        $existingUserIds = User::query()->pluck('id')->flip();

        foreach ($activities as $activity) {
            $entry = $this->mapActivityToHistoryEntry($activity);

            if ($entry === null) {
                continue;
            }

            $createdAt = Carbon::parse($activity->created_at)->timezone('UTC');

            $exists = ContactStatusHistory::query()
                ->where('contact_id', $activity->subject_id)
                ->where('old_status', $entry['old_status'])
                ->where('new_status', $entry['new_status'])
                ->whereBetween('created_at', [
                    $createdAt->copy()->subSeconds(5),
                    $createdAt->copy()->addSeconds(5),
                ])
                ->exists();

            if ($exists) {
                continue;
            }

            $userId = $activity->causer_id !== null && isset($existingUserIds[$activity->causer_id])
                ? (int) $activity->causer_id
                : null;

            ContactStatusHistory::query()->create([
                'contact_id' => $activity->subject_id,
                'user_id' => $userId,
                'old_status' => $entry['old_status'],
                'new_status' => $entry['new_status'],
                'created_at' => $createdAt,
            ]);

            $added++;

            if ($onProgress !== null) {
                $onProgress($added);
            }
        }

        return $added;
    }

    /**
     * @return array{old_status: ?string, new_status: string}|null
     */
    private function mapActivityToHistoryEntry(object $activity): ?array
    {
        $properties = json_decode((string) $activity->properties, true) ?? [];
        $attributes = $properties['attributes'] ?? [];
        $old = $properties['old'] ?? [];

        if ($activity->event === 'created') {
            $newStatus = $attributes['status'] ?? ContactStatus::NOT_PROCESSED->value;

            return [
                'old_status' => null,
                'new_status' => (string) $newStatus,
            ];
        }

        if (! array_key_exists('status', $attributes) || ! array_key_exists('status', $old)) {
            return null;
        }

        if ($attributes['status'] === $old['status']) {
            return null;
        }

        return [
            'old_status' => $old['status'] !== null ? (string) $old['status'] : null,
            'new_status' => (string) $attributes['status'],
        ];
    }
}
