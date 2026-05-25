@php
    $homeUrl = filament()->getHomeUrl();
@endphp

<div class="fi-profile-simple-brand">
    @if ($homeUrl)
        <a {{ \Filament\Support\generate_href_html($homeUrl) }}>
            <x-filament-panels::logo />
        </a>
    @else
        <x-filament-panels::logo />
    @endif
</div>

<style>
    .fi-profile-simple-brand {
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        z-index: 10;
        display: flex;
        height: calc(var(--spacing) * 16);
        align-items: center;
        padding-inline-start: calc(var(--spacing) * 4);
    }

    @media (min-width: 48rem) {
        .fi-profile-simple-brand {
            padding-inline-start: calc(var(--spacing) * 6);
        }
    }

    @media (min-width: 64rem) {
        .fi-profile-simple-brand {
            padding-inline-start: calc(var(--spacing) * 8);
        }
    }

    .fi-profile-simple-brand .fi-logo {
        margin-bottom: 0;
    }
</style>
