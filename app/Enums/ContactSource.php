<?php

namespace App\Enums;

enum ContactSource: string
{
    case SANKIRTANA = 'sankirtana';
    case TEMPLE = 'temple';
    case WEBSITE = 'website';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::SANKIRTANA => 'Санкиртана',
            self::TEMPLE => 'Храм',
            self::WEBSITE => 'Сайт',
            self::OTHER => 'Другое',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }
}
