<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait AppliesContactTableDefaultOrder
{
    public function getTableSortSessionKey(): string
    {
        return parent::getTableSortSessionKey() . '_default_order_v1';
    }

    protected function applySortingToTableQuery(Builder $query): Builder
    {
        if (filled($this->getTableSortColumn())) {
            return parent::applySortingToTableQuery($query);
        }

        return $query->defaultTableOrder();
    }
}
