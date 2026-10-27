<?php

declare(strict_types=1);

namespace App\Http\Middleware\Access;

use Closure;
use Illuminate\Http\Request;
use Support\License\Entitlements;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the capabilities and the forms (`license.capabilities`): turning a
 * capability on, building forms, their submissions (leads) and submitting a
 * form or booking a meeting in a chat. Without a license key on this install
 * they answer 403. Forms, leads and settings are kept, so everything works
 * again as soon as a key is added.
 */
class EnsureCapabilityLicense
{
    public function __construct(private readonly Entitlements $entitlements) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->entitlements->capabilities(), 403, __('This feature is unavailable. Please contact your administrator.'));

        return $next($request);
    }
}
