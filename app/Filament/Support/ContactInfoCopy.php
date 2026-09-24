<?php

namespace App\Filament\Support;

use App\Models\Contact;
use Filament\Actions\Action;
use Illuminate\Support\Js;

final class ContactInfoCopy
{
    public static function action(): Action
    {
        return Action::make('copyContactInfo')
            ->label('Скопировать')
            ->icon('heroicon-o-clipboard-document')
            ->color('gray')
            ->visible(fn (?string $operation): bool => $operation !== 'create')
            ->alpineClickHandler(function (?Contact $record): string {
                return self::clickHandler(self::format(
                    $record?->full_name,
                    $record?->phone,
                    $record?->district,
                ));
            });
    }

    public static function format(mixed $fullName, mixed $phone, mixed $district): string
    {
        return collect([$fullName, $phone, $district])
            ->map(fn (mixed $value): string => trim((string) ($value ?? '')))
            ->filter(fn (string $value): bool => $value !== '')
            ->implode("\n");
    }

    private static function clickHandler(string $fallbackText): string
    {
        $script = <<<'JS'
            (() => {
                const fallback = __FALLBACK__
                const keys = ['full_name', 'phone', 'district']
                const read = (key) => {
                    const input = Array.from(document.querySelectorAll('input, textarea')).find((element) => {
                        return ['wire:model', 'wire:model.blur', 'wire:model.live'].some((attribute) => {
                            return element.getAttribute(attribute) === `data.${key}`
                        })
                    })

                    if (input) {
                        return input.value.trim()
                    }

                    const data = $wire?.data ?? {}

                    if (Object.prototype.hasOwnProperty.call(data, key)) {
                        return String(data[key] ?? '').trim()
                    }

                    return null
                }

                const values = keys.map(read)
                const text = values.every((value) => value === null)
                    ? fallback
                    : values.filter((value) => value !== '').join('\n')

                window.navigator.clipboard.writeText(text)
                $tooltip(__MESSAGE__, {
                    theme: $store.theme,
                    timeout: 2000,
                })
            })()
            JS;

        return str_replace(
            ['__FALLBACK__', '__MESSAGE__'],
            [(string) Js::from($fallbackText), (string) Js::from('Скопировано')],
            $script,
        );
    }
}
