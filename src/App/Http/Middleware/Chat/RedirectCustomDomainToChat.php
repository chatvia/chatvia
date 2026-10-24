<?php

declare(strict_types=1);

namespace App\Http\Middleware\Chat;

use Closure;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentLocator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Symfony\Component\HttpFoundation\Response;

/**
 * An agent's custom domain (`agents.domain`) shows only that agent's hosted
 * chat (`web` group). Any other page opened on it — `/`, the dashboard, the
 * login — redirects to `/chat/{slug}` on the same domain. The app's own host,
 * and hosts that are no agent's custom domain, pass through untouched.
 *
 * The sign-in steps a chat hands off to stay reachable: the emailed sign-in
 * link and the two-factor challenge it can lead to.
 */
final class RedirectCustomDomainToChat
{
    /** Route names of the two-factor challenge (show + submit), from Fortify. */
    private const array SIGN_IN_ROUTES = ['two-factor.login', 'two-factor.login.store'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('chat/*') || $this->isSignInStep($request) || $this->isAppHost($request)) {
            return $next($request);
        }

        $agent = AgentLocator::resolveByHost($request->getHost());

        if (! $agent instanceof Agent) {
            return $next($request);
        }

        return redirect(route('chat.home', ['agentSlug' => $agent->slug ?? $agent->external_id], absolute: false));
    }

    private function isAppHost(Request $request): bool
    {
        return $request->getHost() === Uri::of((string) config('app.url'))->host();
    }

    private function isSignInStep(Request $request): bool
    {
        $magicLinkPath = Str::trim((string) config('magiclink.url.validate_path', 'magiclink'), '/');

        return $request->routeIs(...self::SIGN_IN_ROUTES) || $request->is($magicLinkPath.'/*');
    }
}
