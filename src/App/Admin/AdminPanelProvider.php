<?php

declare(strict_types=1);

namespace App\Admin;

use App\Admin\Pages\Setup;
use App\Identity\Workspace\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\Factory;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Internal staff panel. Access is gated to platform staff (`is_admin`) via
 * {@see User::canAccessPanel()}.
 *
 * The panel signs in on its own `admin` guard, not the shared `web` guard. A
 * session opened by the dashboard login (/login) or the hosted-chat login
 * therefore never reaches the panel, and staff always go through the panel's
 * own login, which asks for the authenticator code when one is set up.
 *
 * Two-factor auth (app authenticator + recovery codes) is optional: an admin
 * turns it on from the profile.
 *
 * A new install then opens on its setup ({@see Setup}): every page leads
 * there until the AI model, search and email are set up, or skipped
 * ({@see RequireSetup}).
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // A closure: the panel is built while Filament's provider boots,
            // before the brand name saved in Settings → Brand is applied.
            ->brandName(fn (): string => config('chatvia.brand.name').' Admin')
            ->brandLogo(fn (): Factory|\Illuminate\Contracts\View\View => view('filament.brand'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('images/favicons/favicon.svg'))
            // The setup shows the mark alone, large, instead of the logo.
            ->renderHook(PanelsRenderHook::SIMPLE_PAGE_START, fn (): Factory|\Illuminate\Contracts\View\View => view('filament.setup-logo'), scopes: Setup::class)
            ->authGuard('admin')
            ->login()
            ->profile()
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable(),
            ])
            // The look of the Chatvia dashboard: its colours, its own copy of
            // Inter and the theme's styles. No top bar: search and the user
            // menu sit in the sidebar, beside the page.
            ->colors(PanelColors::all())
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->font('Inter', url: asset('fonts/inter/inter.css'), provider: LocalFontProvider::class)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->topbar(false)
            ->discoverClusters(in: app_path('Admin/Clusters'), for: 'App\Admin\Clusters')
            ->discoverResources(in: app_path('Admin/Resources'), for: 'App\Admin\Resources')
            ->discoverPages(in: app_path('Admin/Pages'), for: 'App\Admin\Pages')
            ->discoverWidgets(in: app_path('Admin/Widgets'), for: 'App\Admin\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                RequireSetup::class,
            ])
            ->authenticatedRoutes(function (): void {
                // A simple (centered) page, so it is routed here rather than
                // discovered.
                Route::get('setup', Setup::class)->name('setup');
            });
    }
}
