<?php

declare(strict_types=1);

namespace App\Http\Middleware\Access;

use Closure;
use Illuminate\Http\Request;
use Support\License\Entitlements;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the public API and the creating of API keys (`license.api`): without
 * a license key on this install they answer 403. Keys made earlier are kept,
 * so the API works again as soon as a key is added.
 */
class EnsureApiLicense
{
    public function __construct(private readonly Entitlements $entitlements) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->entitlements->api(), 403, __('The API needs a ChatVia.ai license key.'));

        return $next($request);
    }
}
