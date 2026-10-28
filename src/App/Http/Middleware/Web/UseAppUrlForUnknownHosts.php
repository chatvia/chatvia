<?php

declare(strict_types=1);

namespace App\Http\Middleware\Web;

use Closure;
use Domain\Agent\Services\CustomDomainLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global middleware: links the app builds (url(), route(), the e-mailed
 * sign-in, reset and invitation links) take their host from the request, and
 * the request's Host header is whatever the client sent. On a host that is
 * not APP_URL's, every link is built on APP_URL instead, so a forged Host
 * header cannot send someone a sign-in link that leads to another site.
 * An agent's verified custom domain is known too, so its chat pages link and
 * redirect within that domain.
 *
 * Unknown hosts are not refused: the Docker stack reaches the app on an
 * internal address (`BUILTIN_MCP_INTERNAL_URL`, health checks), which must
 */
final readonly class UseAppUrlForUnknownHosts
{
    public function __construct(private CustomDomainLookup $domains) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Set on every request, as the URL generator outlives one request in
        // tests and long-running workers.
        $appUrl = Str::rtrim((string) config('app.url'), '/');

        URL::forceRootUrl($appUrl === '' || $this->isKnownHost($request, $appUrl) ? null : $appUrl);

        return $next($request);
    }

    private function isKnownHost(Request $request, string $appUrl): bool
    {
        $host = Str::lower($request->getHost());

        return $host === Str::lower((string) Uri::of($appUrl)->host())
            || $this->domains->activeAgentIdFor($host) !== null;
    }
}
