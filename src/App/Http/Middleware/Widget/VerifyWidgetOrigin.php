<?php

namespace App\Http\Middleware\Widget;

use Closure;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Embed-origin gate for the widget API: the page that calls the endpoint must
 * be on one of the agent's allowed sites (Origin, then Referer, then Host).
 *
 * The X-AGENT-ID header is required. A missing header, an unknown agent or a
 * site that is not on the list gets a 403. This is a soft control (a script
 * can fake these headers), so every endpoint behind it still scopes its data
 * to the agent and is rate limited.
 */
class VerifyWidgetOrigin
{
    public function __construct(
        private readonly WidgetVerification $verification,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $agentId = $request->header('X-AGENT-ID');

        if (! is_string($agentId) || $agentId === '' || ! $this->verification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        return $next($request);
    }
}
