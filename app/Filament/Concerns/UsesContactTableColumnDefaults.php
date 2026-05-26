<?php

namespace App\Filament\Concerns;

/**
 * Сбрасывает сохранённый в сессии выбор столбцов при смене дефолтов в ресурсе.
 */
trait UsesContactTableColumnDefaults
{
    public function getTableColumnsSessionKey(): string
    {
        return parent::getTableColumnsSessionKey() . '_v2';
    }
}
