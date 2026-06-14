<?php

namespace App\Support\Dashboard;

use App\Models\DashboardMetricSnapshot;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class DashboardMetricsChartDataService
{
    public function __construct(
        private readonly DashboardMetricsSnapshotService $snapshotService,
    ) {}

    /**
     * @param  list<string>|null  $metricKeys
     * @return array{datasets: list<array<string, mixed>>, labels: list<string>}
     */
    public function build(
        Carbon $startDate,
        Carbon $endDate,
        ?array $metricKeys = null,
    ): array {
        $start = $startDate->copy()->timezone('UTC')->startOfDay();
        $end = $endDate->copy()->timezone('UTC')->endOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $buckets = $this->buildBuckets($start, $end);
        $snapshots = $this->loadSnapshots($start, $end);

        $labels = [];
        $datasets = [];

        foreach ($buckets as $bucket) {
            $labels[] = $bucket['label'];
        }

        foreach (DashboardMetricKey::fromValues($metricKeys ?? DashboardMetricKey::defaultValues()) as $metric) {
            $colors = DashboardChartColors::forMetric($metric);
            $data = [];

            foreach ($buckets as $bucket) {
                $data[] = $metric->isFlow()
                    ? $this->sumFlowMetricInBucket($metric, $bucket, $snapshots)
                    : $this->stockMetricAtBucketEnd($metric, $bucket, $snapshots);
            }

            $datasets[] = [
                'label' => $metric->label(),
                'data' => $data,
                'yAxisID' => $metric->isFlow() ? 'y1' : 'y',
                'borderColor' => $colors['borderColor'],
                'backgroundColor' => $colors['backgroundColor'],
                'fill' => false,
                'tension' => 0.25,
                'pointRadius' => 2,
                'pointHoverRadius' => 4,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    /**
     * @return list<array{label: string, start: Carbon, end: Carbon, dates: list<string>}>
     */
    private function buildBuckets(Carbon $start, Carbon $end): array
    {
        $days = $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
        $granularity = match (true) {
            $days <= 45 => 'day',
            $days <= 180 => 'week',
            default => 'month',
        };

        $buckets = [];

        if ($granularity === 'day') {
            foreach (CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay()) as $date) {
                $day = $date->copy()->timezone('UTC');
                $buckets[] = [
                    'label' => $day->locale('ru')->isoFormat('D MMM'),
                    'start' => $day->copy()->startOfDay(),
                    'end' => $day->copy()->endOfDay(),
                    'dates' => [$day->toDateString()],
                ];
            }

            return $buckets;
        }

        if ($granularity === 'week') {
            $cursor = $start->copy()->startOfDay();

            while ($cursor->lte($end)) {
                $bucketStart = $cursor->copy();
                $bucketEnd = $cursor->copy()->addDays(6)->endOfDay();

                if ($bucketEnd->gt($end)) {
                    $bucketEnd = $end->copy();
                }

                $dates = [];
                foreach (CarbonPeriod::create($bucketStart->copy()->startOfDay(), $bucketEnd->copy()->startOfDay()) as $date) {
                    $dates[] = $date->copy()->timezone('UTC')->toDateString();
                }

                $buckets[] = [
                    'label' => $bucketStart->locale('ru')->isoFormat('D MMM') . ' — ' . $bucketEnd->locale('ru')->isoFormat('D MMM'),
                    'start' => $bucketStart,
                    'end' => $bucketEnd,
                    'dates' => $dates,
                ];

                $cursor->addDays(7);
            }

            return $buckets;
        }

        $cursor = $start->copy()->startOfMonth();

        while ($cursor->lte($end)) {
            $bucketStart = $cursor->copy()->startOfDay();
            $bucketEnd = $cursor->copy()->endOfMonth()->endOfDay();

            if ($bucketStart->lt($start)) {
                $bucketStart = $start->copy();
            }

            if ($bucketEnd->gt($end)) {
                $bucketEnd = $end->copy();
            }

            $dates = [];
            foreach (CarbonPeriod::create($bucketStart->copy()->startOfDay(), $bucketEnd->copy()->startOfDay()) as $date) {
                $dates[] = $date->copy()->timezone('UTC')->toDateString();
            }

            $buckets[] = [
                'label' => $cursor->locale('ru')->isoFormat('MMM YYYY'),
                'start' => $bucketStart,
                'end' => $bucketEnd,
                'dates' => $dates,
            ];

            $cursor->addMonthNoOverflow()->startOfMonth();
        }

        return $buckets;
    }

    /**
     * @return Collection<string, Collection<string, DashboardMetricSnapshot>>
     */
    private function loadSnapshots(Carbon $start, Carbon $end): Collection
    {
        return DashboardMetricSnapshot::query()
            ->whereBetween('snapshot_date', [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->get()
            ->groupBy(fn (DashboardMetricSnapshot $snapshot): string => $snapshot->snapshot_date->toDateString())
            ->map(fn (Collection $group): Collection => $group->keyBy('metric_key'));
    }

    /**
     * @param  array{label: string, start: Carbon, end: Carbon, dates: list<string>}  $bucket
     * @param  Collection<string, Collection<string, DashboardMetricSnapshot>>  $snapshots
     */
    private function stockMetricAtBucketEnd(DashboardMetricKey $metric, array $bucket, Collection $snapshots): int
    {
        $dateKey = $bucket['end']->copy()->timezone('UTC')->toDateString();

        return $this->metricValueForDate($metric, $dateKey, $snapshots);
    }

    /**
     * @param  array{label: string, start: Carbon, end: Carbon, dates: list<string>}  $bucket
     * @param  Collection<string, Collection<string, DashboardMetricSnapshot>>  $snapshots
     */
    private function sumFlowMetricInBucket(DashboardMetricKey $metric, array $bucket, Collection $snapshots): int
    {
        $sum = 0;

        foreach ($bucket['dates'] as $dateKey) {
            $sum += $this->metricValueForDate($metric, $dateKey, $snapshots);
        }

        return $sum;
    }

    /**
     * @param  Collection<string, Collection<string, DashboardMetricSnapshot>>  $snapshots
     */
    private function metricValueForDate(DashboardMetricKey $metric, string $dateKey, Collection $snapshots): int
    {
        $daySnapshots = $snapshots->get($dateKey);

        if ($daySnapshots !== null && $daySnapshots->has($metric->value)) {
            return (int) $daySnapshots->get($metric->value)->value;
        }

        return (int) ($this->snapshotService->computeForDate(Carbon::parse($dateKey, 'UTC'))[$metric->value] ?? 0);
    }
}
