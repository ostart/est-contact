<?php

namespace App\Models;

use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Support\PhoneNumberHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Contact extends Model
{
    use LogsActivity;

    protected $attributes = [
        'source' => 'temple',
    ];

    protected static function booted(): void
    {
        static::updating(function (Contact $contact): void {
            if ($contact->isDirty('photo')) {
                $oldPhoto = $contact->getOriginal('photo');
                if ($oldPhoto) {
                    Storage::disk('public')->delete($oldPhoto);
                }
            }
        });

        static::deleting(function (Contact $contact): void {
            if ($contact->photo) {
                Storage::disk('public')->delete($contact->photo);
            }
        });
    }

    protected $fillable = [
        'full_name',
        'photo',
        'phone',
        'email',
        'district',
        'source',
        'status',
        'frozen_until',
        'assigned_leader_id',
        'created_by',
    ];

    /**
     * Предыдущий статус для записи в историю (не сохраняется в БД).
     */
    public ?string $pendingStatusHistoryOld = null;

    protected $casts = [
        'source' => ContactSource::class,
        'status' => ContactStatus::class,
        'frozen_until' => 'datetime:UTC',
        'processing_activity_at' => 'datetime:UTC',
        'overdue_at' => 'datetime:UTC',
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
            ->logOnly(['full_name', 'photo', 'phone', 'email', 'district', 'source', 'status', 'frozen_until', 'assigned_leader_id'])
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

    public static function processingTimeoutDays(): int
    {
        return (int) SystemSetting::get('contact_processing_timeout_days', 30);
    }

    public function touchProcessingActivity(?Carbon $at = null): void
    {
        $this->processing_activity_at = ($at ?? now('UTC'));
        $this->syncOverdueAt();
    }

    public function syncOverdueAt(): void
    {
        if ($this->processing_activity_at === null) {
            $this->overdue_at = null;

            return;
        }

        $this->overdue_at = $this->processing_activity_at->copy()->addDays(static::processingTimeoutDays());
    }

    /**
     * Продлевает дедлайн просрочки на планируемую длительность заморозки (до frozen_until).
     */
    public function extendOverdueAtForFreeze(): void
    {
        if ($this->frozen_until === null) {
            return;
        }

        if ($this->overdue_at === null) {
            $this->syncOverdueAt();
        }

        if ($this->overdue_at === null) {
            return;
        }

        $frozenUntil = Carbon::parse($this->frozen_until)->utc();
        $now = now('UTC');

        if ($frozenUntil->lte($now)) {
            return;
        }

        $this->overdue_at = $this->overdue_at->copy()->addSeconds($now->diffInSeconds($frozenUntil));
    }

    public function applyProcessingActivityOnSave(): void
    {
        if ($this->isDirty('assigned_leader_id')) {
            if ($this->assigned_leader_id) {
                $this->touchProcessingActivity();
            } else {
                $this->processing_activity_at = null;
                $this->syncOverdueAt();
            }
        }

        if (! $this->isDirty('status')) {
            return;
        }

        $oldStatus = $this->resolveOriginalStatus();
        $newStatus = $this->status instanceof ContactStatus
            ? $this->status
            : ContactStatus::from($this->status);

        if ($newStatus === ContactStatus::NOT_PROCESSED || $newStatus->isFinal()) {
            $this->processing_activity_at = null;
            $this->syncOverdueAt();

            return;
        }

        if ($newStatus === ContactStatus::FROZEN) {
            $this->extendOverdueAtForFreeze();

            return;
        }

        if (
            in_array($newStatus, [ContactStatus::ASSIGNED, ContactStatus::IN_PROGRESS], true)
            || $oldStatus === ContactStatus::FROZEN
        ) {
            $this->touchProcessingActivity();
        }
    }

    protected function resolveOriginalStatus(): ?ContactStatus
    {
        $oldStatusValue = $this->getOriginal('status');

        if ($oldStatusValue instanceof ContactStatus) {
            return $oldStatusValue;
        }

        return is_string($oldStatusValue) ? ContactStatus::tryFrom($oldStatusValue) : null;
    }

    public function originalStatus(): ?ContactStatus
    {
        return $this->resolveOriginalStatus();
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

    public function statusBeforeOverdue(): ContactStatus
    {
        $oldStatus = $this->statusHistories()
            ->where('new_status', ContactStatus::OVERDUE->value)
            ->reorder()
            ->orderByDesc('created_at')
            ->value('old_status');

        $status = is_string($oldStatus) ? ContactStatus::tryFrom($oldStatus) : null;

        if ($status === ContactStatus::IN_PROGRESS) {
            return ContactStatus::IN_PROGRESS;
        }

        return ContactStatus::ASSIGNED;
    }

    public function resolveProcessingActivityAtFromHistory(): ?Carbon
    {
        $queueStatuses = ContactStatus::processingQueueValues();
        $frozenStatus = ContactStatus::FROZEN->value;

        $statusAt = $this->statusHistories()
            ->where(function (Builder $query) use ($queueStatuses, $frozenStatus): void {
                $query->whereIn('new_status', $queueStatuses)
                    ->orWhere('old_status', $frozenStatus);
            })
            ->reorder()
            ->orderByDesc('created_at')
            ->value('created_at');

        $commentAt = null;
        if ($this->assigned_leader_id) {
            $commentAt = $this->comments()
                ->where('user_id', $this->assigned_leader_id)
                ->max('created_at');
        }

        $candidates = array_filter([
            $statusAt ? Carbon::parse($statusAt) : null,
            $commentAt ? Carbon::parse($commentAt) : null,
        ]);

        if ($candidates === []) {
            return null;
        }

        return collect($candidates)->max();
    }

    public function recalculateProcessingActivityFromHistory(): bool
    {
        $activityAt = $this->resolveProcessingActivityAtFromHistory();

        if ($activityAt === null) {
            $this->forceFill([
                'processing_activity_at' => null,
                'overdue_at' => null,
            ])->saveQuietly();

            return false;
        }

        $this->touchProcessingActivity($activityAt);
        $this->saveQuietly();

        return true;
    }

    public function shouldDisplayOverdueAt(): bool
    {
        return in_array($this->status, [
            ContactStatus::ASSIGNED,
            ContactStatus::IN_PROGRESS,
            ContactStatus::OVERDUE,
            ContactStatus::FROZEN,
        ], true);
    }

    public function resolveOverdueAt(): ?Carbon
    {
        if (! $this->shouldDisplayOverdueAt()) {
            return null;
        }

        if ($this->overdue_at !== null) {
            return $this->overdue_at;
        }

        $activityAt = $this->resolveProcessingActivityAtFromHistory();

        return $activityAt?->copy()->addDays(static::processingTimeoutDays());
    }

    public function isOverdue(): bool
    {
        if (! in_array($this->status, [ContactStatus::ASSIGNED, ContactStatus::IN_PROGRESS], true)) {
            return false;
        }

        if ($this->overdue_at === null) {
            return false;
        }

        return $this->overdue_at->lte(now());
    }

    /**
     * Сортировка по колонке «Дата просрочки»: только релевантные статусы, null в конце.
     */
    public function scopeOrderByOverdueAt(Builder $query, string $direction = 'asc'): Builder
    {
        $table = $query->getModel()->getTable();
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $days = static::processingTimeoutDays();

        $displayStatuses = array_map(
            fn (ContactStatus $status): string => "'{$status->value}'",
            [
                ContactStatus::ASSIGNED,
                ContactStatus::IN_PROGRESS,
                ContactStatus::OVERDUE,
                ContactStatus::FROZEN,
            ],
        );
        $statusIn = implode(', ', $displayStatuses);

        $driver = $query->getConnection()->getDriverName();

        $deadlineExpression = match ($driver) {
            'sqlite' => "CASE WHEN {$table}.status IN ({$statusIn}) THEN COALESCE({$table}.overdue_at, datetime({$table}.processing_activity_at, '+{$days} days')) END",
            'pgsql' => "CASE WHEN {$table}.status IN ({$statusIn}) THEN COALESCE({$table}.overdue_at, {$table}.processing_activity_at + interval '{$days} days') END",
            default => "CASE WHEN {$table}.status IN ({$statusIn}) THEN COALESCE({$table}.overdue_at, DATE_ADD({$table}.processing_activity_at, INTERVAL {$days} DAY)) END",
        };

        return $query
            ->orderByRaw("({$deadlineExpression}) IS NULL ASC")
            ->orderByRaw("({$deadlineExpression}) ".strtoupper($direction));
    }

    /**
     * Сортировка по умолчанию для таблиц контактов: группа статуса → ответственный → дата изменения (сначала новые).
     */
    public function scopeDefaultTableOrder(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->leftJoin(
                'users as contact_sort_leaders',
                "{$table}.assigned_leader_id",
                '=',
                'contact_sort_leaders.id',
            )
            ->orderByRaw(ContactStatus::defaultTableSortGroupSql("{$table}.status"))
            ->orderBy('contact_sort_leaders.name')
            ->orderByDesc("{$table}.updated_at")
            ->select("{$table}.*");
    }
}

