<?php

namespace App\Filament\Support;

use App\Filament\Contracts\PersistsContactTablePreferences;
use Filament\Actions;
use Filament\Forms\Components;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Table;

class ContactTablePreferencesAction
{
    public static function sortAction(): Actions\Action
    {
        return Actions\Action::make('configureTableSort')
            ->label('Сортировка')
            ->icon('heroicon-o-bars-arrow-down')
            ->modalHeading('Настройка сортировки')
            ->modalDescription('Задайте порядок сортировки: сначала по одной колонке, затем по следующей и т.д. Настройки сохраняются для вашего аккаунта.')
            ->modalSubmitActionLabel('Сохранить')
            ->extraModalWindowAttributes([
                'class' => 'fi-contact-table-sort-modal',
            ])
            ->fillForm(fn (PersistsContactTablePreferences $livewire): array => [
                'sort_layers' => filled($livewire->getTableSortLayers())
                    ? $livewire->getTableSortLayers()
                    : [['column' => null, 'direction' => 'asc']],
            ])
            ->form([
                Components\Repeater::make('sort_layers')
                    ->label('Уровни сортировки')
                    ->schema([
                        Components\Select::make('column')
                            ->label('Колонка')
                            ->options(fn (PersistsContactTablePreferences $livewire): array => self::sortableColumnOptions($livewire->getTable()))
                            ->required()
                            ->searchable()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                        Components\Select::make('direction')
                            ->label('Направление')
                            ->options([
                                'asc' => 'По возрастанию',
                                'desc' => 'По убыванию',
                            ])
                            ->default('asc')
                            ->required(),
                    ])
                    ->reorderable()
                    ->addActionLabel('Добавить уровень')
                    ->defaultItems(1)
                    ->minItems(0),
            ])
            ->action(function (array $data, PersistsContactTablePreferences $livewire): void {
                $layers = collect($data['sort_layers'] ?? [])
                    ->filter(fn (array $layer): bool => filled($layer['column'] ?? null))
                    ->map(fn (array $layer): array => [
                        'column' => (string) $layer['column'],
                        'direction' => ($layer['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
                    ])
                    ->values()
                    ->all();

                $livewire->saveTableSortLayers($layers);
            })
            ->modalFooterActions(fn (Actions\Action $action): array => [
                $action->getModalSubmitAction(),
                $action->getModalCancelAction(),
                self::resetTablePreferencesAction(),
            ]);
    }

    protected static function resetTablePreferencesAction(): Actions\Action
    {
        return Actions\Action::make('resetTablePreferences')
            ->label('По умолчанию')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Сбросить настройки таблицы?')
            ->modalDescription('Будут восстановлены колонки, их порядок и сортировка по умолчанию.')
            ->modalSubmitActionLabel('Сбросить')
            ->cancelParentActions()
            ->action(fn (PersistsContactTablePreferences $livewire) => $livewire->resetAllContactTablePreferences());
    }

    /**
     * @return array<string, string>
     */
    public static function sortableColumnOptions(Table $table): array
    {
        $options = [];

        foreach ($table->getColumnsLayout() as $component) {
            if ($component instanceof ColumnGroup) {
                foreach ($component->getColumns() as $column) {
                    self::collectSortableColumnOption($options, $column);
                }

                continue;
            }

            if ($component instanceof Column) {
                self::collectSortableColumnOption($options, $component);
            }
        }

        return $options;
    }

    /**
     * @param  array<string, string>  $options
     */
    protected static function collectSortableColumnOption(array &$options, Column $column): void
    {
        if ($column->isHidden() || ! $column->isSortable()) {
            return;
        }

        $label = $column->getLabel();

        if (blank($label)) {
            return;
        }

        $options[$column->getName()] = (string) $label;
    }
}
