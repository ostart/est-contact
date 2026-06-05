<?php

namespace App\Filament\Concerns;

use App\Models\UserTablePreference;
use Illuminate\Database\Eloquent\Builder;

/**
 * Персональные настройки таблицы контактов: колонки, их порядок и многоуровневая сортировка.
 */
trait HasPersistedContactTablePreferences
{
    /**
     * @var array<int, array{column: string, direction: string}>
     */
    public array $tableSortLayers = [];

    abstract protected function getContactTablePreferencesKey(): string;

    /**
     * @return array<int, array{column: string, direction: string}>
     */
    public function getTableSortLayers(): array
    {
        return $this->tableSortLayers;
    }

    public function mountHasPersistedContactTablePreferences(): void
    {
        $this->loadContactTableSortPreferences();
    }

    public function initTableColumnManager(): void
    {
        $this->hydrateContactTablePreferenceSession();

        parent::initTableColumnManager();
    }

    public function getTableColumnsSessionKey(): string
    {
        return parent::getTableColumnsSessionKey() . '_v4';
    }

    public function getTableSortSessionKey(): string
    {
        return parent::getTableSortSessionKey() . '_v1';
    }

    public function sortTable(?string $column = null, ?string $direction = null): void
    {
        $this->tableSortLayers = [];
        $this->saveContactTablePreference(sorts: []);

        parent::sortTable($column, $direction);
    }

    /**
     * @param  array<int, array{column: string, direction: string}>  $layers
     */
    public function saveTableSortLayers(array $layers): void
    {
        $this->tableSortLayers = $layers;
        $this->tableSort = null;
        $this->saveContactTablePreference(sorts: $layers);
        $this->resetPage();
    }

    public function resetAllContactTablePreferences(): void
    {
        $userId = auth()->id();

        if ($userId) {
            UserTablePreference::query()
                ->where('user_id', $userId)
                ->where('table_key', $this->getContactTablePreferencesKey())
                ->delete();
        }

        session()->forget($this->getTableColumnsSessionKey());
        session()->forget($this->getHasReorderedTableColumnsSessionKey());

        $this->tableSortLayers = [];
        $this->tableSort = null;
        $this->tableColumns = $this->getDefaultTableColumnState();

        if ($this->hasReorderableTableColumns()) {
            $this->updateTableColumns();
        }

        $this->resetPage();
    }

    protected function applySortingToTableQuery(Builder $query): Builder
    {
        if ($this->getTable()->isGroupsOnly()) {
            return $query;
        }

        if ($this->isTableReordering()) {
            return $query->orderBy(
                $this->getTable()->getReorderColumn(),
                $this->getTable()->getReorderDirection(),
            );
        }

        if (filled($this->tableSortLayers)) {
            foreach ($this->tableSortLayers as $layer) {
                $columnName = $layer['column'] ?? null;

                if (blank($columnName)) {
                    continue;
                }

                $column = $this->getTable()->getSortableVisibleColumn($columnName);

                if (! $column) {
                    continue;
                }

                $direction = ($layer['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
                $column->applySort($query, $direction);
            }

            if (! $this->getTable()->hasDefaultKeySort()) {
                return $query;
            }

            return $this->appendDefaultKeySort($query);
        }

        if (filled($this->getTableSortColumn())) {
            return parent::applySortingToTableQuery($query);
        }

        return $query->defaultTableOrder();
    }

    /**
     * @return array<int, array{type: string, name: string, label: string, isHidden: bool, isToggled: bool, isToggleable: bool, isToggledHiddenByDefault: ?bool, columns?: array<int, array{type: string, name: string, label: string, isHidden: bool, isToggled: bool, isToggleable: bool, isToggledHiddenByDefault: ?bool}>}>
     */
    protected function loadTableColumnsFromSession(): array
    {
        $columns = $this->getContactTablePreferenceRecord()?->columns;

        if (filled($columns)) {
            return $columns;
        }

        return $this->getDefaultTableColumnState();
    }

    protected function persistTableColumns(): void
    {
        if (! $this->getTable()->persistsColumnsInSession()) {
            return;
        }

        session()->put(
            $this->getTableColumnsSessionKey(),
            $this->tableColumns,
        );

        $this->saveContactTablePreference(columns: $this->tableColumns);
    }

    protected function persistHasReorderedTableColumns(bool $wasReordered = false): void
    {
        $hasReordered = $wasReordered || $this->hasReorderedTableColumns();

        session()->put(
            $this->getHasReorderedTableColumnsSessionKey(),
            $hasReordered,
        );

        $this->saveContactTablePreference(hasReorderedColumns: $hasReordered);
    }

    protected function hydrateContactTablePreferenceSession(): void
    {
        if ($this->getContactTablePreferenceRecord()?->has_reordered_columns) {
            session()->put($this->getHasReorderedTableColumnsSessionKey(), true);
        }
    }

    protected function loadContactTableSortPreferences(): void
    {
        $sorts = $this->getContactTablePreferenceRecord()?->sorts;

        if (filled($sorts)) {
            $this->tableSortLayers = $sorts;
        }
    }

    protected function getContactTablePreferenceRecord(): ?UserTablePreference
    {
        $userId = auth()->id();

        if (! $userId) {
            return null;
        }

        return UserTablePreference::query()
            ->where('user_id', $userId)
            ->where('table_key', $this->getContactTablePreferencesKey())
            ->first();
    }

    /**
     * @param  array<int, array{type: string, name: string, label: string, isHidden: bool, isToggled: bool, isToggleable: bool, isToggledHiddenByDefault: ?bool, columns?: array<int, array{type: string, name: string, label: string, isHidden: bool, isToggled: bool, isToggleable: bool, isToggledHiddenByDefault: ?bool}>}>|null  $columns
     * @param  array<int, array{column: string, direction: string}>|null  $sorts
     */
    protected function saveContactTablePreference(
        ?array $columns = null,
        ?array $sorts = null,
        ?bool $hasReorderedColumns = null,
    ): void {
        $userId = auth()->id();

        if (! $userId) {
            return;
        }

        $attributes = [];

        if ($columns !== null) {
            $attributes['columns'] = $columns;
        }

        if ($sorts !== null) {
            $attributes['sorts'] = $sorts;
        }

        if ($hasReorderedColumns !== null) {
            $attributes['has_reordered_columns'] = $hasReorderedColumns;
        }

        if ($attributes === []) {
            return;
        }

        UserTablePreference::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'table_key' => $this->getContactTablePreferencesKey(),
            ],
            $attributes,
        );
    }

    protected function appendDefaultKeySort(Builder $query): Builder
    {
        $qualifiedKeyName = $query->getModel()->getQualifiedKeyName();
        $sortDirection = ($this->getTable()->getDefaultSortDirection() ?? $this->getTableSortDirection()) === 'desc'
            ? 'desc'
            : 'asc';

        foreach ($query->getQuery()->orders ?? [] as $order) {
            if (($order['column'] ?? null) === $qualifiedKeyName) {
                return $query;
            }

            if (
                is_string($order['column'] ?? null) &&
                str($order['column'] ?? null)->afterLast('.')->is(
                    str($qualifiedKeyName)->afterLast('.')
                )
            ) {
                return $query;
            }
        }

        return $query->orderBy($qualifiedKeyName, $sortDirection);
    }
}
