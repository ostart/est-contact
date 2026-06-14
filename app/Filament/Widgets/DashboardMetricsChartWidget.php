<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\DashboardMetricKey;
use App\Support\Dashboard\DashboardMetricsChartDataService;
use App\Support\Dashboard\DashboardMetricsChartPreferenceService;
use App\Support\Dashboard\DashboardMetricsChartPreferences;
use Carbon\Carbon;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Illuminate\Validation\ValidationException;

class DashboardMetricsChartWidget extends ChartWidget
{
    use HasFiltersSchema;

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '640px';

    protected ?string $pollingInterval = null;

    protected bool $isCollapsible = true;

    protected static bool $isLazy = false;

    protected bool $hasDeferredFilters = true;

    protected string $view = 'filament.widgets.dashboard-metrics-chart';

    private bool $isHydratingChartFilters = false;

    public function mount(): void
    {
        $this->isHydratingChartFilters = true;

        $saved = $this->resolveChartFilters();

        $this->filters = $saved;
        $this->deferredFilters = $saved;

        parent::mount();

        $this->isHydratingChartFilters = false;
    }

    public function rendering(): void
    {
        $this->restoreChartFiltersIfBlanked();

        $this->updateChartData();
    }

    public function getHeading(): ?string
    {
        return 'Динамика метрик';
    }

    public function getDescription(): ?string
    {
        return 'Контакты и пользователи за выбранный период. Цвета соответствуют плашкам статистики.';
    }

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema->components([
            Fieldset::make('Период')
                ->schema([
                    DatePicker::make('startDate')
                        ->label('С')
                        ->required()
                        ->maxDate(fn (): string => now('Europe/Moscow')->toDateString())
                        ->native(false)
                        ->closeOnDateSelection(),
                    DatePicker::make('endDate')
                        ->label('По')
                        ->required()
                        ->maxDate(fn (): string => now('Europe/Moscow')->toDateString())
                        ->native(false)
                        ->closeOnDateSelection(),
                ]),
            Fieldset::make('Контакты')
                ->schema([
                    CheckboxList::make('contactMetrics')
                        ->label('Метрики контактов')
                        ->options(DashboardMetricKey::contactOptions())
                        ->columns(2)
                        ->bulkToggleable(),
                ]),
            Fieldset::make('Пользователи')
                ->schema([
                    CheckboxList::make('userMetrics')
                        ->label('Метрики пользователей')
                        ->options(DashboardMetricKey::userOptions())
                        ->columns(2)
                        ->bulkToggleable(),
                ]),
        ]);
    }

    public function applyFilters(): void
    {
        $this->filters = $this->deferredFilters ?? [];
        $this->cachedData = null;
        $this->dataChecksum = $this->generateDataChecksum();

        $this->persistFiltersIfValid();
    }

    public function resetFiltersForm(): void
    {
        $this->isHydratingChartFilters = true;

        app(DashboardMetricsChartPreferenceService::class)->resetForUser(auth()->user());

        $defaults = DashboardMetricsChartPreferences::defaults()->toFilterArray();

        $this->filters = $defaults;
        $this->deferredFilters = $defaults;
        $this->cachedData = null;
        $this->dataChecksum = $this->generateDataChecksum();

        $this->isHydratingChartFilters = false;
    }

    public function getChartStateKey(): string
    {
        return md5(json_encode($this->filters ?? []));
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $preferences = DashboardMetricsChartPreferences::fromFilterArray($this->filters ?? []);

        $data = app(DashboardMetricsChartDataService::class)->build(
            Carbon::parse($preferences->startDate, 'Europe/Moscow'),
            Carbon::parse($preferences->endDate, 'Europe/Moscow'),
            $preferences->metrics,
        );

        if ($data['datasets'] === []) {
            $data['datasets'] = [[
                'label' => 'Нет данных',
                'data' => [0],
                'borderColor' => '#d1d5db',
                'backgroundColor' => 'rgba(209, 213, 219, 0.15)',
                'yAxisID' => 'y',
            ]];
            $data['labels'] = $data['labels'] !== [] ? $data['labels'] : ['—'];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getOptions(): ?array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'boxWidth' => 12,
                        'padding' => 14,
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Накопительные показатели',
                    ],
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'beginAtZero' => true,
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                    'title' => [
                        'display' => true,
                        'text' => 'Новые за период',
                    ],
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }

    private function restoreChartFiltersIfBlanked(): void
    {
        if ($this->isHydratingChartFilters) {
            return;
        }

        $this->filters = $this->restoreFilterState($this->filters);

        if ($this->hasDeferredFilters()) {
            $this->deferredFilters = $this->restoreFilterState($this->deferredFilters);
        }
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return array<string, mixed>
     */
    private function restoreFilterState(?array $filters): array
    {
        if ($this->chartMetricsLookBlanked($filters)) {
            return $this->resolveChartFilters();
        }

        return $this->applyDefaultDates($filters ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveChartFilters(): array
    {
        return app(DashboardMetricsChartPreferenceService::class)
            ->resolveForUser(auth()->user())
            ->toFilterArray();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function applyDefaultDates(array $filters): array
    {
        if (! filled($filters['startDate'] ?? null)) {
            $filters['startDate'] = DashboardMetricsChartPreferences::defaultStartDate();
        }

        if (! filled($filters['endDate'] ?? null)) {
            $filters['endDate'] = DashboardMetricsChartPreferences::defaultEndDate();
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    private function chartMetricsLookBlanked(?array $filters): bool
    {
        $filters ??= [];

        return ($filters['contactMetrics'] ?? []) === []
            && ($filters['userMetrics'] ?? []) === [];
    }

    private function persistFiltersIfValid(): void
    {
        if ($this->isHydratingChartFilters) {
            return;
        }

        $user = auth()->user();

        if ($user === null) {
            return;
        }

        try {
            $validated = DashboardMetricsChartPreferences::validatedFilterArray($this->filters ?? []);
        } catch (ValidationException) {
            return;
        }

        app(DashboardMetricsChartPreferenceService::class)->saveForUser(
            $user,
            DashboardMetricsChartPreferences::fromFilterArray($validated),
        );
    }
}
