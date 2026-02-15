<?php

if (! function_exists('format_datetime_moscow')) {
    /**
     * Форматирует дату/время для отображения в московском часовом поясе.
     * Ожидается, что в БД значения хранятся в UTC.
     */
    function format_datetime_moscow(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return \Carbon\Carbon::parse($value, 'UTC')
            ->timezone('Europe/Moscow')
            ->format('d.m.Y H:i');
    }
}
