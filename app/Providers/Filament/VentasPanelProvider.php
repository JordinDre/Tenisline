<?php

namespace App\Providers\Filament;

use Filament\Panel;
use App\Models\User;
use Filament\PanelProvider;
use Kenepa\Banner\BannerPlugin;
use Filament\Navigation\MenuItem;
use App\Http\Middleware\VentasPanel;
use Filament\Support\Enums\MaxWidth;
use Filament\Http\Middleware\Authenticate;
use Kenepa\ResourceLock\ResourceLockPlugin;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Kainiklas\FilamentScout\FilamentScoutPlugin;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use App\Http\Middleware\BloquearSistemaPorVentaPendiente;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;

class VentasPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('ventas')
            ->path('ventas')
            ->profile()
            ->login()
            ->brandName('TenisLine')
            // PWA: manifest y sugerencia de instalar la app en el celular
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn () => view('pwa.meta', ['config' => \App\Services\Pwa\ManifestService::generate()]),
            )
            ->renderHook(\Filament\View\PanelsRenderHook::BODY_END, fn () => view('pwa.instalar'))
            ->brandLogo(asset('images/logo-panel-negro.png'))
            ->darkModeBrandLogo(asset('images/logo-panel-blanco.png'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('images/icono.png'))
            ->font('Poppins')
            ->databaseTransactions()
            ->maxContentWidth(MaxWidth::Full)
            ->spa()
            ->unsavedChangesAlerts()
            ->discoverResources(in: app_path('Filament/Ventas/Resources'), for: 'App\\Filament\\Ventas\\Resources')
            ->discoverPages(in: app_path('Filament/Ventas/Pages'), for: 'App\\Filament\\Ventas\\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Ventas/Widgets'), for: 'App\\Filament\\Ventas\\Widgets')
            ->widgets([])
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
                \Hasnayeen\Themes\Http\Middleware\SetTheme::class,
                BloquearSistemaPorVentaPendiente::class,
                /* VentasPanel::class, */
            ])
            ->sidebarFullyCollapsibleOnDesktop()
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('15rem')
            ->databaseNotifications()
            ->authMiddleware([
                Authenticate::class,
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label('Tienda')
                    ->url(fn (): string => '/catalogo')
                    ->icon('tabler-building-store'),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->breadcrumbs(false)
            ->plugins([
                FilamentFullCalendarPlugin::make(),
                \Hasnayeen\Themes\ThemesPlugin::make()->canViewThemesPage(fn () => auth()->user() ? auth()->user()->hasAnyRole(User::ROLES_ADMIN) : false),
                ResourceLockPlugin::make(),
                BannerPlugin::make()->persistsBannersInDatabase()->disableBannerManager(),
                /* FilamentScoutPlugin::make()
                    ->useMeilisearch(), */
            ]);
    }
}
