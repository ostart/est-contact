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
}

