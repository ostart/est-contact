<?php

namespace App\Filament\Support;

use Illuminate\Database\Eloquent\Builder;

final class ContactTableSearch
{
    public static function applyLike(Builder $query, string $column, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $query->where(
            $query->getModel()->qualifyColumn($column),
            'like',
            '%'.addcslashes($search, '%_\\').'%',
        );
    }
}
