<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserWarning;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class UserModerationActivity
{
    /**
     * Записи журнала о блокировке пользователя (источник счётчика «Баны» на дашборде).
     */
    public static function banLogQuery(): Builder
    {
        return Activity::query()
            ->where('subject_type', User::class)
            ->where('event', 'updated')
            ->where(function (Builder $query): void {
                $query
                    ->whereRaw("JSON_EXTRACT(properties, '$.attributes.is_banned') = true")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(properties, '$.attributes.is_banned')) = 'true'")
                    ->orWhereRaw("JSON_EXTRACT(properties, '$.attributes.is_banned') = 1");
            });
    }

    public static function banLogQueryForUser(User $user): Builder
    {
        return static::banLogQuery()->where('subject_id', $user->id);
    }

    /**
     * Подзапрос COUNT для колонки «Баны» в виджете дашборда.
     */
    public static function banCountSubquery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('activity_log')
            ->selectRaw('COUNT(*)')
            ->whereColumn('activity_log.subject_id', 'users.id')
            ->where('activity_log.subject_type', User::class)
            ->where('activity_log.event', 'updated')
            ->where(function ($query): void {
                $query
                    ->whereRaw("JSON_EXTRACT(activity_log.properties, '$.attributes.is_banned') = true")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(activity_log.properties, '$.attributes.is_banned')) = 'true'")
                    ->orWhereRaw("JSON_EXTRACT(activity_log.properties, '$.attributes.is_banned') = 1");
            });
    }

    public static function deleteBanLogsForUser(User $user): int
    {
        return static::banLogQueryForUser($user)->delete();
    }

    /**
     * @param  Collection<int, int>|array<int, int>  $warningIds
     */
    public static function deleteWarningLogs(Collection|array $warningIds): int
    {
        $ids = collect($warningIds)->filter()->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        return Activity::query()
            ->where('subject_type', UserWarning::class)
            ->whereIn('subject_id', $ids)
            ->delete();
    }
}
