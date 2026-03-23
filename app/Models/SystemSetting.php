<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('system_settings');
        });

        static::deleted(function () {
            Cache::forget('system_settings');
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = Cache::rememberForever('system_settings', function () {
            return static::all()->pluck('value', 'key');
        });

        return $settings->get($key, $default);
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Включена ли рассылка уведомлений на email (настройка «Почтовый сервер»).
     *
     * Читаем из БД без вечного кэша. При дубликатах одного ключа важно брать последнюю запись:
     * pluck в get() для коллекции оставляет последнее значение, а value() без orderBy — первую строку.
     */
    public static function mailNotificationsEnabled(): bool
    {
        $value = static::query()
            ->where('key', 'mail_notifications_enabled')
            ->orderByDesc('id')
            ->value('value');

        if ($value === null) {
            $value = static::get('mail_notifications_enabled', '0');
        }

        return filter_var((string) $value, FILTER_VALIDATE_BOOLEAN);
    }
}

