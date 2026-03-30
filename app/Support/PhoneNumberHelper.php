<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Propaganistas\LaravelPhone\PhoneNumber;

final class PhoneNumberHelper
{
    public const DEFAULT_REGION = 'RU';

    /** Регионы для контактов (ввод менеджером). */
    public const CONTACT_REGIONS = ['RU', 'BY', 'KZ', 'UA', 'US'];

    /**
     * Нормализация в E.164 для сохранения в БД. Для пустой строки — null.
     *
     * @param  array<string>  $regions
     */
    public static function normalize(?string $value, array $regions = [self::DEFAULT_REGION]): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            $phone = new PhoneNumber($value, $regions);
            if (! $phone->isValid()) {
                return null;
            }

            return $phone->formatE164();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Пытается получить E.164 для полного совпадения при поиске.
     *
     * @param  array<string>  $regions
     */
    public static function tryNormalizeForSearch(string $value, array $regions = [self::DEFAULT_REGION]): ?string
    {
        try {
            $phone = new PhoneNumber(trim($value), $regions);
            if (! $phone->isValid()) {
                return null;
            }

            return $phone->formatE164();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Условие поиска по телефону: полный номер через пакет и/или по цифрам.
     *
     * @param  array<string>  $regions
     */
    public static function applyColumnSearch(Builder $query, string $column, string $search, array $regions = [self::DEFAULT_REGION]): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $qualified = $query->getModel()->qualifyColumn($column);
        $e164 = self::tryNormalizeForSearch($search, $regions);
        $digits = preg_replace('/\D+/', '', $search) ?? '';

        $query->where(function (Builder $q) use ($qualified, $e164, $digits, $search): void {
            $has = false;
            if ($e164 !== null) {
                $q->where($qualified, $e164);
                $has = true;
            }
            if ($digits !== '') {
                if ($has) {
                    $q->orWhere($qualified, 'like', '%'.$digits.'%');
                } else {
                    $q->where($qualified, 'like', '%'.$digits.'%');
                }
                $has = true;
            }
            if (! $has) {
                $q->where($qualified, 'like', '%'.addcslashes($search, '%_\\').'%');
            }
        });
    }
}
