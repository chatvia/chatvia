<?php

declare(strict_types=1);

namespace App\Http\Middleware\Access;

use Closure;
use Illuminate\Http\Request;
use Support\License\Entitlements;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the routes of an Enterprise feature (`license.enterprise`): without
 * an Enterprise key on this install they answer 403. The pages that show these
 * features stay open and say what is locked.
 */
class EnsureEnterpriseLicense
{
    public function __construct(private readonly Entitlements $entitlements) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->entitlements->enterprise(), 403, __('This feature is unavailable. Please contact your administrator.'));

        return $next($request);
    }
}
