<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;

final class PhoneDisplay
{
    public static function tableColumn(TextColumn $column): TextColumn
    {
        return $column->fontFamily(FontFamily::Mono);
    }

    public static function textEntry(TextEntry $entry): TextEntry
    {
        return $entry->fontFamily(FontFamily::Mono);
    }

    public static function textInput(TextInput $input): TextInput
    {
        return $input->extraInputAttributes(['class' => 'fi-font-mono']);
    }

    public static function html(string $phone): string
    {
        return '<span class="fi-font-mono">'.e($phone).'</span>';
    }

    public static function markdown(string $phone): string
    {
        return '`'.$phone.'`';
    }
}
