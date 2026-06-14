<?php

namespace App\Support\Dashboard;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class DashboardMetricsChartPreferences
{
    /**
     * @param  list<string>  $metrics
     */
    public function __construct(
        public readonly array $metrics,
        public readonly string $startDate,
        public readonly string $endDate,
    ) {}

    public static function defaults(): self
    {
        return new self(
            metrics: DashboardMetricKey::defaultValues(),
            startDate: self::defaultStartDate(),
            endDate: self::defaultEndDate(),
        );
    }

    public static function defaultStartDate(): string
    {
        return now('Europe/Moscow')->subDays(30)->toDateString();
    }

    public static function defaultEndDate(): string
    {
        return now('Europe/Moscow')->toDateString();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function fromPersistedFilterState(array $state): self
    {
        $defaults = self::defaults();

        return new self(
            metrics: self::resolveMetrics($state, $defaults->metrics),
            startDate: $defaults->startDate,
            endDate: $defaults->endDate,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function fromFilterArray(array $filters): self
    {
        $defaults = self::defaults();

        return new self(
            metrics: self::resolveMetrics($filters, $defaults->metrics),
            startDate: (string) ($filters['startDate'] ?? $defaults->startDate),
            endDate: (string) ($filters['endDate'] ?? $defaults->endDate),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toFilterArray(): array
    {
        return [
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'contactMetrics' => array_values(array_intersect($this->metrics, DashboardMetricKey::contactValues())),
            'userMetrics' => array_values(array_intersect($this->metrics, DashboardMetricKey::userValues())),
        ];
    }

    /**
     * @return array{contactMetrics: list<string>, userMetrics: list<string>}
     */
    public function toPersistedFilterState(): array
    {
        $filters = $this->toFilterArray();

        return [
            'contactMetrics' => $filters['contactMetrics'],
            'userMetrics' => $filters['userMetrics'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function validatedFilterArray(array $filters): array
    {
        Validator::make($filters, [
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'contactMetrics' => ['nullable', 'array'],
            'contactMetrics.*' => ['string', Rule::in(DashboardMetricKey::contactValues())],
            'userMetrics' => ['nullable', 'array'],
            'userMetrics.*' => ['string', Rule::in(DashboardMetricKey::userValues())],
            'metrics' => ['nullable', 'array'],
            'metrics.*' => ['string', Rule::in(DashboardMetricKey::options())],
        ])->validate();

        $resolved = self::fromFilterArray($filters);

        if ($resolved->metrics === []) {
            throw ValidationException::withMessages([
                'contactMetrics' => 'Выберите хотя бы одну метрику.',
            ]);
        }

        return $resolved->toFilterArray();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $fallback
     * @return list<string>
     */
    private static function resolveMetrics(array $filters, array $fallback): array
    {
        $contactMetrics = DashboardMetricKey::filterValidValues((array) ($filters['contactMetrics'] ?? []));
        $userMetrics = DashboardMetricKey::filterValidValues((array) ($filters['userMetrics'] ?? []));

        if ($contactMetrics !== [] || $userMetrics !== []) {
            $metrics = array_values(array_unique([...$contactMetrics, ...$userMetrics]));

            return $metrics !== [] ? $metrics : $fallback;
        }

        $legacyMetrics = DashboardMetricKey::filterValidValues((array) ($filters['metrics'] ?? []));

        return $legacyMetrics !== [] ? $legacyMetrics : $fallback;
    }
}
