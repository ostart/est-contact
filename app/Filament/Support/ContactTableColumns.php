<?php

namespace App\Filament\Support;

use App\Enums\ContactStatus;
use App\Models\Contact;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class ContactTableColumns
{
    public static function status(): TextColumn
    {
        return TextColumn::make('status')
            ->label('Статус')
            ->badge()
            ->formatStateUsing(fn ($state): string => ($state instanceof ContactStatus ? $state : ContactStatus::from($state))->getLabel())
            ->color(fn ($state): string => ($state instanceof ContactStatus ? $state : ContactStatus::from($state))->getColor())
            ->searchable(query: function (Builder $query, string $search): void {
                ContactTableSearch::applyStatusSearch($query, $search);
            })
            ->sortable();
    }

    public static function statusFilter(): SelectFilter
    {
        return SelectFilter::make('status')
            ->label('Статус')
            ->options(ContactStatus::options())
            ->native(false)
            ->searchable();
    }

    public static function overdueAt(): TextColumn
    {
        return TextColumn::make('overdue_at')
            ->label('Дата просрочки')
            ->getStateUsing(fn (Contact $record): ?\Illuminate\Support\Carbon => $record->resolveOverdueAt())
            ->formatStateUsing(fn ($state): string => filled($state) ? (format_datetime_moscow($state) ?? '—') : '—')
            ->placeholder('—')
            ->sortable(query: function (Builder $query, string $direction): Builder {
                return $query->orderByOverdueAt($direction);
            })
            ->toggleable();
    }
}
