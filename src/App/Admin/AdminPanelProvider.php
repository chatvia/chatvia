<?php

declare(strict_types=1);

namespace App\Admin;

use App\Admin\Pages\Setup;
use App\Admin\Settings\ModelLogoController;
use App\Http\Middleware\Web\DenyCrossOriginFraming;
use App\Identity\Workspace\Models\User;
use Domain\Llm\Services\ModelsDevCatalog;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
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
use Saade\FilamentLaravelLog\FilamentLaravelLogPlugin;

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
 * there until AI, models, documents, storage and email are set up, or
 * skipped ({@see RequireSetup}).
 *
 * Logs reads and clears the files in storage/logs, so it opens only for the
 * same staff as Horizon ({@see StaffToolsAccess}), and only once an admin
 * turns it on (Settings → Platform → Development).
 *
 * Tools, Status, Logs and Settings sit below a divider in the sidebar, apart
 * from the pages about the install's data ({@see SYSTEM_NAVIGATION_GROUP}).
 * Settings comes last.
 */
class AdminPanelProvider extends PanelProvider
{
    /**
     * The sidebar group of the pages that run the install itself. It has no
     * heading: the theme draws a line above it.
     */
    public const string SYSTEM_NAVIGATION_GROUP = 'system';

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // The app's name (Settings → Platform), in every page title: "Agents - Acme Admin".
            ->brandName(fn (): string => config('app.name').' Admin')
            ->brandLogo(fn (): Factory|\Illuminate\Contracts\View\View => view('filament.brand'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('images/favicons/favicon.svg'))
            // The setup shows the mark alone, large, above its card, instead of the logo.
            ->renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, fn (): Factory|\Illuminate\Contracts\View\View => view('filament.setup-logo'), scopes: Setup::class)
            ->authGuard('admin')
            ->login()
            ->profile()
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable(),
            ])
            // The look of the ChatVia.ai dashboard: its colours, its own copy of
            // Inter and the theme's styles. No top bar: search and the user
            // menu sit in the sidebar, beside the page.
            ->colors(PanelColors::all())
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->font('Inter', url: asset('fonts/inter/inter.css'), provider: LocalFontProvider::class)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->topbar(false)
            // The sidebar's search finds the Settings sections and options too.
            ->globalSearch(AdminGlobalSearchProvider::class)
            ->navigationGroups([
                self::SYSTEM_NAVIGATION_GROUP => NavigationGroup::make()
                    ->extraSidebarAttributes(['class' => 'cv-sidebar-group--divided']),
            ])
            ->discoverClusters(in: app_path('Admin/Clusters'), for: 'App\Admin\Clusters')
            ->discoverResources(in: app_path('Admin/Resources'), for: 'App\Admin\Resources')
            ->discoverPages(in: app_path('Admin/Pages'), for: 'App\Admin\Pages')
            ->discoverWidgets(in: app_path('Admin/Widgets'), for: 'App\Admin\Widgets')
            // The log files are the whole install's, not one workspace's.
            ->plugin(
                FilamentLaravelLogPlugin::make()
                    ->authorize(fn (): bool => config('chatvia.admin.logs_page_enabled') === true
                        && app(StaffToolsAccess::class)->allows())
                    ->navigationGroup(self::SYSTEM_NAVIGATION_GROUP)
                    ->navigationSort(99)
            )
            ->middleware([
                // The admin panel runs outside the `web` group, so it gets
                // the always-on framing and HSTS headers here.
                DenyCrossOriginFraming::class,
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
                Route::get('model-logos/{logo}.svg', ModelLogoController::class)
                    ->where('logo', ModelsDevCatalog::LOGO_PATTERN)
                    ->name('model-logo');
            });
    }
}
