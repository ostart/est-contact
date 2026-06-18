<?php

namespace App\Providers\Filament;

use Filament\Enums\ThemeMode;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentAsset;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        FilamentAsset::appVersion((string) max(
            filemtime(resource_path('css/filament-table-viewport-scrollbar.css')),
            filemtime(resource_path('js/filament-table-viewport-scrollbar.js')),
        ));

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->registration(\App\Filament\Pages\Auth\Register::class)
            ->emailVerification()
            ->passwordReset()
            ->profile(\App\Filament\Pages\EditProfile::class)
            ->colors([
                'primary' => Color::Blue,
                'azure' => Color::hex('#C5EBFA'),
                'purple' => Color::Purple,
                'brown' => Color::hex('#92400e'),
            ])
            ->font(
                'Inter Variable',
                url: asset('fonts/inter/index.css'),
                provider: LocalFontProvider::class,
                preload: [asset('fonts/inter/inter-latin-wght-normal-NRMW37G5.woff2')],
            )
            ->brandName('БВ Контакт')
            ->homeUrl(fn (): ?string => auth()->check() ? auth()->user()->getFilamentHomeUrl() : null)
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                fn (): \Illuminate\Contracts\View\View => view('filament.components.profile-simple-brand'),
                scopes: \App\Filament\Pages\EditProfile::class,
            )
            ->defaultThemeMode(ThemeMode::Light)
            ->sidebarWidth('14rem')
            ->maxContentWidth(Width::Full)
            ->assets([
                Css::make(
                    'table-viewport-scrollbar',
                    resource_path('css/filament-table-viewport-scrollbar.css'),
                ),
                Js::make(
                    'table-viewport-scrollbar',
                    resource_path('js/filament-table-viewport-scrollbar.js'),
                ),
            ])
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <style>
                        .fi-sidebar .fi-sidebar-nav {
                            padding-inline: 0.75rem;
                            padding-block: 1rem;
                            row-gap: 0.5rem;
                        }

                        .fi-sidebar .fi-sidebar-item-btn {
                            gap: 0.5rem;
                            justify-content: flex-start;
                            padding-block: 0.5rem;
                            padding-inline: 0.5rem;
                        }

                        .fi-sidebar .fi-sidebar-item-label {
                            flex: 1 1 auto;
                            min-width: 0;
                        }

                        .fi-sidebar .fi-sidebar-item-badge-ctn {
                            margin-inline-start: auto;
                            flex-shrink: 0;
                        }

                        @media (min-width: 1024px) {
                            .fi-body-has-navigation:not(.fi-body-has-top-navigation) .fi-main {
                                padding-inline-start: 0.5rem;
                                padding-inline-end: 1rem;
                            }

                            .fi-body-has-navigation:not(.fi-body-has-top-navigation) .fi-page-header-main-ctn {
                                padding-block-start: 1.5rem;
                                padding-block-end: 1rem;
                            }
                        }

                        .fi-contact-table-sort-modal .fi-modal-footer-actions {
                            width: 100%;
                            display: flex;
                            flex-wrap: wrap;
                            align-items: center;
                        }

                        .fi-contact-table-sort-modal .fi-modal-footer-actions > :last-child {
                            margin-inline-start: auto;
                        }

                        .fi-sc-section.fi-contact-status-section .fi-section-content {
                            padding-block: calc(var(--spacing) * 3);
                            padding-inline: calc(var(--spacing) * 6);
                        }

                        .fi-sc-section.fi-contact-status-section .fi-section-content .fi-sc-flex.fi-contact-status-row {
                            flex-direction: row !important;
                            flex-wrap: wrap;
                            align-items: center !important;
                            justify-content: flex-start !important;
                            gap: 1rem !important;
                        }

                        .fi-sc-section.fi-contact-status-section .fi-contact-status-row > div {
                            flex: 0 0 auto !important;
                            width: auto !important;
                        }

                        .fi-sc-section.fi-contact-status-section .fi-contact-status-row .fi-sc-actions {
                            gap: 0 !important;
                            height: auto !important;
                        }

                        .fi-contact-status-label {
                            font-size: var(--text-base);
                            line-height: var(--tw-leading, var(--text-base--line-height));
                            font-weight: var(--font-weight-semibold);
                            color: var(--gray-950);
                        }

                        .fi-contact-status-label:where(.dark, .dark *) {
                            color: var(--color-white);
                        }

                        .fi-sc-section.fi-contact-status-history-section {
                            padding-bottom: calc(var(--spacing) * 12);
                        }
                    </style>
                    HTML),
            )
            ->favicon(asset('favicon.ico'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                \App\Filament\Widgets\ContactsStatsWidget::class,
                \App\Filament\Widgets\UsersStatsWidget::class,
                \App\Filament\Widgets\DashboardMetricsChartWidget::class,
                \App\Filament\Widgets\UsersTableWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\EnsureUserIsApproved::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsLivewireComponent(\App\Filament\Livewire\DatabaseNotifications::class)
            ->databaseNotificationsPolling('30s')
            ->lazyLoadedDatabaseNotifications(false)
            ->spa();
    }
}
