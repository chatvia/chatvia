<?php

declare(strict_types=1);

namespace App\Http\Middleware\Access;

use Closure;
use Illuminate\Http\Request;
use Support\License\Entitlements;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the dashboard's sentiment, tone, topics and sources analytics data
 * (`license.analytics`). They need a license key on this install; without
 * one this answers 403. The pages themselves still open and say why they are
 * locked. The public API needs a key as a whole (`license.api`).
 */
class EnsureAnalyticsLicense
{
    public function __construct(private readonly Entitlements $entitlements) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->entitlements->advancedAnalytics(), 403, __('This feature is unavailable. Please contact your administrator.'));

        return $next($request);
    }
}
