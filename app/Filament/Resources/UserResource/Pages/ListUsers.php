<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Size;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Создать')
                ->icon('heroicon-o-user-plus')
                ->iconSize(IconSize::Large)
                ->size(Size::Large)
                ->color('primary'),
        ];
    }
}
