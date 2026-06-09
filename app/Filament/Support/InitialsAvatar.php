<?php

namespace App\Filament\Support;

class InitialsAvatar
{
    /**
     * @var array<int, string>
     */
    protected const COLORS = [
        '#6366f1',
        '#8b5cf6',
        '#ec4899',
        '#f59e0b',
        '#10b981',
        '#3b82f6',
        '#ef4444',
        '#14b8a6',
        '#f97316',
        '#06b6d4',
    ];

    public static function initialsFromName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/[\s\/]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) >= 2) {
            return mb_strtoupper(
                mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1),
            );
        }

        return mb_strtoupper(mb_substr($name, 0, min(2, mb_strlen($name))));
    }

    public static function backgroundColor(string $name): string
    {
        $index = abs(crc32(mb_strtolower(trim($name) ?: '?'))) % count(self::COLORS);

        return self::COLORS[$index];
    }

    public static function dataUri(string $name, int $size = 32): string
    {
        $initials = htmlspecialchars(static::initialsFromName($name), ENT_XML1, 'UTF-8');
        $background = static::backgroundColor($name);
        $fontSize = max(10, (int) round($size * 0.38));

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}">
  <rect width="{$size}" height="{$size}" fill="{$background}" rx="9999"/>
  <text x="50%" y="50%" dy="0.35em" text-anchor="middle" fill="#ffffff" font-family="system-ui,sans-serif" font-size="{$fontSize}" font-weight="600">{$initials}</text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
