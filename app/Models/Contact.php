<?php

namespace App\Models;

use App\Enums\ContactStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Contact extends Model
{
    use LogsActivity;

    protected $fillable = [
        'full_name',
        'phone',
        'email',
        'district',
        'status',
        'assigned_leader_id',
        'created_by',
    ];

    protected $casts = [
        'status' => ContactStatus::class,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['full_name', 'phone', 'email', 'district', 'status', 'assigned_leader_id'])
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
        return $this->hasMany(ContactComment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ContactStatusHistory::class);
    }

    public function isOverdue(): bool
    {
        if ($this->status !== ContactStatus::ASSIGNED) {
            return false;
        }

        $timeout = SystemSetting::get('contact_processing_timeout', 30);
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

