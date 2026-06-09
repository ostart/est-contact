<?php

namespace App\Livewire;

use App\Enums\CommentsContext;
use App\Models\ContactComment;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class ContactCommentsTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public int $contactId;

    public string $context;

    protected string $view = 'livewire.contact-comments-table';

    public function mount(int $contactId, string $context): void
    {
        $this->contactId = $contactId;
        $this->context = $context;
    }

    public function getCommentsContext(): CommentsContext
    {
        return CommentsContext::from($this->context);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ContactComment::query()
                    ->where('contact_id', $this->contactId)
                    ->with('user')
                    ->orderByDesc('created_at')
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->formatStateUsing(fn ($state) => format_datetime_moscow($state))
                    ->width('9rem'),

                TextColumn::make('user.name')
                    ->label('Автор')
                    ->default('—')
                    ->width('8rem'),

                TextColumn::make('comment')
                    ->label('Комментарий')
                    ->wrap()
                    ->grow(),
            ])
            ->recordActions($this->getRecordActions())
            ->headerActions($this->getHeaderActions())
            ->recordActionsColumnLabel('')
            ->paginated(false)
            ->emptyStateHeading('Комментариев пока нет')
            ->emptyStateDescription(null);
    }

    /**
     * @return array<Action>
     */
    protected function getRecordActions(): array
    {
        $context = $this->getCommentsContext();

        $actions = [
            Action::make('view')
                ->icon('heroicon-o-eye')
                ->iconButton()
                ->tooltip('Просмотр')
                ->modalHeading(fn (ContactComment $record): string => ($record->user?->name ?? '—').' · '.format_datetime_moscow($record->created_at))
                ->modalContent(fn (ContactComment $record): HtmlString => new HtmlString(
                    '<p class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">'.e($record->comment).'</p>'
                ))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Закрыть'),
        ];

        if ($context->canEdit()) {
            $actions[] = Action::make('edit')
                ->icon('heroicon-o-pencil-square')
                ->iconButton()
                ->tooltip('Редактировать')
                ->fillForm(fn (ContactComment $record): array => [
                    'comment' => $record->comment,
                ])
                ->form([
                    Textarea::make('comment')
                        ->label('Комментарий')
                        ->required()
                        ->rows(5),
                ])
                ->modalSubmitActionLabel('Сохранить')
                ->modalCancelActionLabel('Отмена')
                ->action(function (ContactComment $record, array $data): void {
                    $record->update(['comment' => $data['comment']]);

                    Notification::make()
                        ->title('Комментарий обновлён')
                        ->success()
                        ->send();
                });
        }

        if ($context->canDelete()) {
            $actions[] = Action::make('delete')
                ->icon('heroicon-o-trash')
                ->iconButton()
                ->tooltip('Удалить')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Удалить комментарий?')
                ->modalSubmitActionLabel('Удалить')
                ->modalCancelActionLabel('Отмена')
                ->action(function (ContactComment $record): void {
                    $record->delete();

                    Notification::make()
                        ->title('Комментарий удалён')
                        ->success()
                        ->send();
                });
        }

        return $actions;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        $context = $this->getCommentsContext();

        if (! $context->canAdd()) {
            return [];
        }

        if ($context === CommentsContext::LeaderView && ! auth()->user()->hasRole('leader')) {
            return [];
        }

        return [
            Action::make('add')
                ->label('Добавить')
                ->icon('heroicon-o-plus')
                ->form([
                    Textarea::make('comment')
                        ->label('Комментарий')
                        ->required()
                        ->rows(3),
                ])
                ->modalSubmitActionLabel('Добавить')
                ->modalCancelActionLabel('Отмена')
                ->action(function (array $data): void {
                    ContactComment::query()->create([
                        'contact_id' => $this->contactId,
                        'user_id' => auth()->id(),
                        'comment' => $data['comment'],
                        'created_at' => Carbon::now('UTC'),
                    ]);

                    Notification::make()
                        ->title('Комментарий добавлен')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function render(): View
    {
        return view($this->view);
    }
}
