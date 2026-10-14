<?php

declare(strict_types=1);

namespace App\Admin;

use App\Admin\Pages\Setup;
use App\Admin\Settings\SetupStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends an admin to the setup of a new install ({@see Setup}) until it is
 * done or skipped, so the install never runs without an AI model. Signing
 * out, the profile and setting up two-factor authentication stay open.
 */
final class RequireSetup
{
    /** The panel routes an admin can open before the setup is done. */
    private const array OPEN_ROUTES = [
        'filament.admin.setup',
        'filament.admin.auth.logout',
        'filament.admin.auth.profile',
        'filament.admin.auth.multi-factor-authentication.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! SetupStatus::current() instanceof SetupStatus && ! $request->routeIs(self::OPEN_ROUTES)) {
            return redirect()->route('filament.admin.setup');
        }

        return $next($request);
    }
}
