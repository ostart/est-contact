<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Enums\ContactStatus;
use App\Filament\Concerns\HasPersistedContactTablePreferences;
use App\Filament\Contracts\PersistsContactTablePreferences;
use App\Filament\Resources\ContactResource;
use App\Filament\Support\ContactTablePreferencesAction;
use App\Filament\Support\YandexMapAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListContacts extends ListRecords implements PersistsContactTablePreferences
{
    use HasPersistedContactTablePreferences;

    public const TAB_ALL = 'all';

    public const TAB_REJECTED = 'rejected';

    /** @deprecated Renamed to {@see self::TAB_ALL}; normalized on mount. */
    private const LEGACY_TAB_ALL = 'active';

    protected static string $resource = ContactResource::class;

    protected function getContactTablePreferencesKey(): string
    {
        return 'contacts';
    }

    public function mount(): void
    {
        parent::mount();

        $this->normalizeActiveTab();
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return self::TAB_ALL;
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $user = auth()->user();

        if (! $user->hasRole('leader')) {
            return [];
        }

        return [
            self::TAB_ALL => Tab::make('Все'),

            self::TAB_REJECTED => Tab::make('Отказы')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', ContactStatus::FAILED->value)
                    ->with('latestFailedStatusHistory')),
        ];
    }

    public function isAllTab(): bool
    {
        return in_array($this->activeTab, [self::TAB_ALL, self::LEGACY_TAB_ALL], true);
    }

    public function isRejectedTab(): bool
    {
        return $this->activeTab === self::TAB_REJECTED;
    }

    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();

        $this->normalizeActiveTab();

        if (! auth()->user()->can_use_contact_filters) {
            return;
        }

        if ($this->isRejectedTab()) {
            if (is_array($this->tableFilters)) {
                data_set($this->tableFilters, 'my_contacts.isActive', false);
            }

            return;
        }

        if ($this->isAllTab() && is_array($this->tableFilters)) {
            data_set($this->tableFilters, 'my_contacts.isActive', true);
        }
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            ContactTablePreferencesAction::sortAction(),
        ];

        if (auth()->user()->can_use_map) {
            $actions[] = YandexMapAction::make();
        }

        return $actions;
    }

    private function normalizeActiveTab(): void
    {
        if ($this->activeTab === self::LEGACY_TAB_ALL) {
            $this->activeTab = self::TAB_ALL;
        }
    }
}
