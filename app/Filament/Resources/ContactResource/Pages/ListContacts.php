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

    protected static string $resource = ContactResource::class;

    protected function getContactTablePreferencesKey(): string
    {
        return 'contacts';
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'active';
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

        $excludeFinalAndFrozen = fn (Builder $query): Builder => $query->whereNotIn('status', [
            ContactStatus::SUCCESS->value,
            ContactStatus::FAILED->value,
            ContactStatus::FROZEN->value,
        ]);

        return [
            'active' => Tab::make('Активные')
                ->modifyQueryUsing(fn (Builder $query): Builder => $excludeFinalAndFrozen($query)),

            'rejected' => Tab::make('Отказы')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', ContactStatus::FAILED->value)
                    ->with('latestFailedStatusHistory')),
        ];
    }

    public function isRejectedTab(): bool
    {
        return $this->activeTab === 'rejected';
    }

    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();

        if (! auth()->user()->can_use_contact_filters) {
            return;
        }

        if ($this->activeTab === 'rejected') {
            if (is_array($this->tableFilters)) {
                data_set($this->tableFilters, 'my_contacts.isActive', false);
            }

            return;
        }

        if ($this->activeTab === 'active' && is_array($this->tableFilters)) {
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
}
