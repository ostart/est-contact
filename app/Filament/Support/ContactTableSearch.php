<?php

namespace App\Filament\Support;

use App\Enums\ContactStatus;
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

    public static function applyStatusSearch(Builder $query, string $search): void
    {
        $values = ContactStatus::valuesMatchingLabelSearch($search);

        if ($values === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn($query->getModel()->qualifyColumn('status'), $values);
    }
}
