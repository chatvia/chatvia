<?php

declare(strict_types=1);

namespace App\Http\Middleware\Access;

use Closure;
use Domain\Agent\Services\CustomDomainLookup;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards saving, checking and removing an agent's custom domain
 * (`license.custom-domains`). It answers 403 without a license key, or until
 * an admin turns custom domains on. Saved domains are kept either way.
 */
class EnsureCustomDomainsAvailable
{
    public function __construct(private readonly CustomDomainLookup $domains) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->domains->isAvailable(), 403, __('This feature is unavailable. Please contact your administrator.'));

        return $next($request);
    }
}
