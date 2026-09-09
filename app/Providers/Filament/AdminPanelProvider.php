<?php

namespace App\Providers\Filament;

use App\Enums\AdminPanel;
use App\Filament\Admin\Auth\Login;
use App\Http\Middleware\SetLocale;
use App\Models\Restaurant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The per-restaurant admin panel, served at t1.restaurant-app.com/admin.
 *
 * The subdomain identifies the tenant, so every resource registered here is
 * automatically scoped to the restaurant in the URL.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id(AdminPanel::Admin->value)
            ->path(AdminPanel::Admin->path())
            ->tenant(Restaurant::class, slugAttribute: 'slug')
            ->tenantDomain('{tenant:slug}.'.config('app.domain'))
            ->login(Login::class)
            ->brandName(AdminPanel::Admin->brandName())
            ->brandLogo(fn (): View => view('filament.brand', ['panel' => AdminPanel::Admin]))
            ->brandLogoHeight('2rem')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            // Panels are for laptops and larger; a phone is shown a door
            // rather than a layout nobody designed. See .ai/rules/filament.md.
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): View => view('filament.desktop-only'),
            )
            // The language this panel is worked in. Menu names come out of
            // translated columns, so this has to be a server round trip.
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): View => view('filament.language-switcher'),
            )
            // A resource with no policy, or a policy missing the method being
            // asked about, is refused rather than waved through. Without this a
            // page added tomorrow is open to anyone who can reach the panel,
            // and nothing says so.
            ->strictAuthorization()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                // Panels do not run the `web` group, so the middleware that
                // reads the language cookie is listed here too.
                SetLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
