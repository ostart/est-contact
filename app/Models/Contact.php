<?php

namespace App\Models;

use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Support\PhoneNumberHelper;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Contact extends Model
{
    use LogsActivity;

    protected $attributes = [
        'source' => 'temple',
    ];

    protected $fillable = [
        'full_name',
        'phone',
        'email',
        'district',
        'source',
        'status',
        'frozen_until',
        'assigned_leader_id',
        'created_by',
    ];

    protected $casts = [
        'source' => ContactSource::class,
        'status' => ContactStatus::class,
        'frozen_until' => 'datetime',
    ];

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: function (?string $value): array {
                if ($value === null || trim($value) === '') {
                    return ['phone' => $value];
                }

                $normalized = PhoneNumberHelper::normalize(trim($value), PhoneNumberHelper::CONTACT_REGIONS);

                return ['phone' => $normalized ?? trim($value)];
            },
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['full_name', 'phone', 'email', 'district', 'source', 'status', 'frozen_until', 'assigned_leader_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function assignedLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_leader_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ContactComment::class)->orderByDesc('created_at');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ContactStatusHistory::class)->orderByDesc('created_at');
    }

    public function processingStartedAt(): ?\Illuminate\Support\Carbon
    {
        return $this->statusHistories()
            ->whereIn('new_status', ContactStatus::processingQueueValues())
            ->reorder()
            ->orderBy('created_at')
            ->value('created_at');
    }

    public function statusBeforeFrozen(): ContactStatus
    {
        $oldStatus = $this->statusHistories()
            ->where('new_status', ContactStatus::FROZEN->value)
            ->reorder()
            ->orderByDesc('created_at')
            ->value('old_status');

        $status = is_string($oldStatus) ? ContactStatus::tryFrom($oldStatus) : null;

        if ($status === ContactStatus::IN_PROGRESS) {
            return ContactStatus::IN_PROGRESS;
        }

        return ContactStatus::ASSIGNED;
    }

    public function isOverdue(): bool
    {
        if (! in_array($this->status, [ContactStatus::ASSIGNED, ContactStatus::IN_PROGRESS], true)) {
            return false;
        }

        $startedAt = $this->processingStartedAt();

        if ($startedAt === null) {
            return false;
        }

        $timeout = (int) SystemSetting::get('contact_processing_timeout_days', 30);

        return now()->diffInDays($startedAt) > $timeout;
    }
}

