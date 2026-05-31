<?php

namespace App\Filament\Support;

use App\Enums\CommentsContext;
use App\Livewire\ContactCommentsTable;
use App\Models\Contact;
use Filament\Forms\Components;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Schemas\Components\Livewire;

class ContactCommentsSection
{
    public static function tableComponent(CommentsContext $context): Livewire
    {
        return SchemaComponents\Livewire::make(ContactCommentsTable::class)
            ->data(fn (?Contact $record): array => [
                'contactId' => $record?->getKey() ?? 0,
                'context' => $context->value,
            ])
            ->visible(fn (?Contact $record, string $operation): bool => $operation === 'edit' && filled($record?->getKey()))
            ->columnSpanFull()
            ->key(fn (?Contact $record): string => 'contact-comments-'.($record?->getKey() ?? 'none'));
    }

    public static function infolistTableComponent(CommentsContext $context): Livewire
    {
        return SchemaComponents\Livewire::make(ContactCommentsTable::class)
            ->data(fn (Contact $record): array => [
                'contactId' => $record->getKey(),
                'context' => $context->value,
            ])
            ->columnSpanFull()
            ->key(fn (Contact $record): string => 'contact-comments-'.$record->getKey());
    }

    public static function initialCommentField(): Components\Textarea
    {
        return Components\Textarea::make('initial_comment')
            ->label('Комментарий')
            ->rows(3)
            ->columnSpanFull();
    }

    public static function formSection(CommentsContext $context): SchemaComponents\Section
    {
        return SchemaComponents\Section::make('Комментарии')
            ->schema([
                static::tableComponent($context),
                static::initialCommentField()
                    ->visibleOn('create'),
            ])
            ->collapsible();
    }

    public static function infolistSection(CommentsContext $context): SchemaComponents\Section
    {
        return SchemaComponents\Section::make('Комментарии')
            ->schema([
                static::infolistTableComponent($context),
            ])
            ->collapsible()
            ->columnSpanFull();
    }
}
