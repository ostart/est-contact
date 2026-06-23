<?php

namespace App\Filament\Support;

use App\Models\Contact;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class ContactTableColumns
{
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
