<?php

namespace App\Support\Dashboard;

/**
 * Hex-цвета линий графика, соответствующие цветам плашек Filament на дашборде.
 */
final class DashboardChartColors
{
    /**
     * @return array{borderColor: string, backgroundColor: string}
     */
    public static function forMetric(DashboardMetricKey $metric): array
    {
        $border = match ($metric->color()) {
            'primary' => '#3b82f6',
            'gray' => '#6b7280',
            'info' => '#0ea5e9',
            'azure' => '#38bdf8',
            'teal' => '#14b8a6',
            'brown' => '#92400e',
            'warning' => '#f59e0b',
            'purple' => '#a855f7',
            'success' => '#22c55e',
            'danger' => '#ef4444',
            default => '#6b7280',
        };

        return [
            'borderColor' => $border,
            'backgroundColor' => self::withAlpha($border, 0.15),
        ];
    }

    private static function withAlpha(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, $alpha);
    }
}
