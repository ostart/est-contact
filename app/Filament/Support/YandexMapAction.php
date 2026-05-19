<?php

namespace App\Filament\Support;

use Filament\Actions;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Size;
use Illuminate\Support\HtmlString;

class YandexMapAction
{
    public static function make(): Actions\Action
    {
        return Actions\Action::make('map')
            ->label('Карта')
            ->icon('heroicon-o-map')
            ->iconSize(IconSize::Large)
            ->size(Size::Large)
            ->color('gray')
            ->modalHeading('Карта')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Закрыть')
            ->modalWidth('7xl')
            ->modalContent(fn (): HtmlString => new HtmlString(
                '<iframe src="'.e(route('map.embed')).'" '
                .'class="w-full rounded-lg border border-gray-200" '
                .'style="height: min(75vh, 720px);" '
                .'title="Яндекс Карта" loading="lazy"></iframe>'
            ));
    }
}
