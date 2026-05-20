<?php

namespace App\Filament\Support;

use App\Filament\Resources\ManagementResource;
use App\Models\ContactComment;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class ContactCommentsSection
{
    public static function commentsRepeater(): Repeater
    {
        return Repeater::make('comments')
            ->label('')
            ->relationship()
            ->schema([
                Forms\Components\Placeholder::make('user_name')
                    ->label('Автор')
                    ->content(fn ($record) => $record?->user?->name ?? '—')
                    ->visibleOn('edit'),

                Forms\Components\Textarea::make('comment')
                    ->label('Комментарий')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Hidden::make('user_id')
                    ->default(fn () => auth()->id()),
            ])
            ->addActionLabel('Добавить комментарий')
            ->addable(fn (string $operation): bool => $operation === 'create')
            ->deletable(true)
            ->reorderable(false)
            ->defaultItems(0)
            ->columnSpanFull()
            ->deleteAction(function (Action $action, string $operation): Action {
                if ($operation !== 'edit') {
                    return $action;
                }

                return $action
                    ->requiresConfirmation()
                    ->modalHeading('Удалить комментарий?')
                    ->modalSubmitActionLabel('Удалить')
                    ->modalCancelActionLabel('Отмена')
                    ->action(function (array $arguments, Repeater $component, EditRecord $livewire): void {
                        $itemKey = $arguments['item'];
                        $record = $component->getCachedExistingRecords()[$itemKey] ?? null;

                        if ($record === null && is_numeric($itemKey)) {
                            $record = ContactComment::query()
                                ->whereKey($itemKey)
                                ->where('contact_id', $livewire->getRecord()->getKey())
                                ->first();
                        }

                        if ($record !== null) {
                            $record->delete();
                        }

                        Notification::make()
                            ->title('Комментарий удалён')
                            ->success()
                            ->send();

                        $livewire->redirect(ManagementResource::getUrl('edit', ['record' => $livewire->getRecord()]));
                    });
            });
    }

    public static function addCommentAction(): Action
    {
        return Action::make('add_comment')
            ->label('Добавить комментарий')
            ->form([
                Forms\Components\Textarea::make('comment')
                    ->label('Комментарий')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data, EditRecord $livewire): void {
                $livewire->getRecord()->comments()->create([
                    'comment' => $data['comment'],
                    'user_id' => auth()->id(),
                    'created_at' => Carbon::now('UTC'),
                ]);

                Notification::make()
                    ->title('Комментарий добавлен')
                    ->success()
                    ->send();

                $livewire->redirect(ManagementResource::getUrl('edit', ['record' => $livewire->getRecord()]));
            })
            ->modalSubmitActionLabel('Добавить')
            ->modalCancelActionLabel('Отмена');
    }
}
