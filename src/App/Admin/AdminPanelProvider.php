<?php

declare(strict_types=1);

namespace App\Admin;

use App\Identity\Workspace\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\App;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Internal staff panel. Access is gated to UserType::Admin via
 * {@see User::canAccessPanel()}.
 *
 * The panel signs in on its own `admin` guard, not the shared `web` guard. A
 * session opened by the dashboard login (/login) or the hosted-chat login
 * therefore never reaches the panel, and staff always go through the panel's
 * own login, which asks for the authenticator code.
 *
 * Two-factor auth (app authenticator + recovery codes) is required: an admin
 * without it is sent to the set-up page on first sign-in and can't use the
 * panel until it is done.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(config('chatvia.brand.name').' Admin')
            ->authGuard('admin')
            ->login()
            ->profile()
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable(),
            ], isRequired: App::isProduction())
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverClusters(in: app_path('Admin/Clusters'), for: 'App\Admin\Clusters')
            ->discoverResources(in: app_path('Admin/Resources'), for: 'App\Admin\Resources')
            ->discoverPages(in: app_path('Admin/Pages'), for: 'App\Admin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Admin/Widgets'), for: 'App\Admin\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
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
            ]);
    }
}
