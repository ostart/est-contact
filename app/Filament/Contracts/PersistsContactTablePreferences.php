<?php

namespace App\Filament\Contracts;

use Filament\Tables\Table;

interface PersistsContactTablePreferences
{
    /**
     * @return array<int, array{column: string, direction: string}>
     */
    public function getTableSortLayers(): array;

    /**
     * @param  array<int, array{column: string, direction: string}>  $layers
     */
    public function saveTableSortLayers(array $layers): void;

    public function resetAllContactTablePreferences(): void;

    public function getTable(): Table;
}
