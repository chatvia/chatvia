<?php

declare(strict_types=1);

namespace App\Http\Middleware\Access;

use Closure;
use Illuminate\Http\Request;
use Support\License\Entitlements;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards taking over a chat (`license.handoff`). Live chat needs a license
 * key on this install; without one this answers 403. Only the takeover is
 * guarded: the person who already holds a chat can still reply and give it
 * back, so a lapsed key never cuts a visitor off mid-chat.
 */
class EnsureHandoffLicense
{
    public function __construct(private readonly Entitlements $entitlements) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->entitlements->handoff(), 403, __('This feature is unavailable. Please contact your administrator.'));

        return $next($request);
    }
}
