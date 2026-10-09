<?php

namespace App\Http\Middleware\Chat;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends agent traffic to the hosted chat (`web` group). A request outside
 * `/chat` that arrives on an agent subdomain, or names an agent with
 * `?agent=`, is redirected to that agent's chat home, so the marketing site
 * and dashboard never render on an agent's address.
 *
 * The sign-in steps the hosted chat hands off to are the exception: the
 * emailed sign-in link and the two-factor challenge it leads to must open on
 * the agent's address, or a user with two-factor authentication could never
 * finish signing in to a protected agent.
 */
class RedirectToAgent
{
    /**
     * Route names of the two-factor challenge (show + submit), from Fortify.
     */
    private const array SIGN_IN_ROUTES = ['two-factor.login', 'two-factor.login.store'];

    public function handle(Request $request, Closure $next): Response
    {
        if (str_starts_with($request->path(), 'chat') || $this->isSignInStep($request)) {
            return $next($request);
        }

        $subdomain = $request->subdomain();

        if ($subdomain && $subdomain !== 'www') {
            return to_route('chat.home');
        }

        $explicit = $request->query('agent');

        if (is_string($explicit) && $explicit !== '') {
            return to_route('chat.home', ['agent' => $explicit]);
        }

        return $next($request);
    }

    private function isSignInStep(Request $request): bool
    {
        $magicLinkPath = trim((string) config('magiclink.url.validate_path', 'magiclink'), '/');

        return $request->routeIs(...self::SIGN_IN_ROUTES) || $request->is($magicLinkPath.'/*');
    }
}
