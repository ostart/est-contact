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
        'assigned_leader_id',
        'created_by',
    ];

    protected $casts = [
        'source' => ContactSource::class,
        'status' => ContactStatus::class,
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
            ->logOnly(['full_name', 'phone', 'email', 'district', 'source', 'status', 'assigned_leader_id'])
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

    public function isOverdue(): bool
    {
        if ($this->status !== ContactStatus::ASSIGNED) {
            return false;
        }

        $timeout = SystemSetting::get('contact_processing_timeout_days', 30);
        $assignedDate = $this->statusHistories()
            ->where('new_status', ContactStatus::ASSIGNED->value)
            ->latest()
            ->first()?->created_at;

        if (!$assignedDate) {
            return false;
        }

        return now()->diffInDays($assignedDate) > $timeout;
    }
}

