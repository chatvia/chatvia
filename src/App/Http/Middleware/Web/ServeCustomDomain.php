<?php

declare(strict_types=1);

namespace App\Http\Middleware\Web;

use Closure;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentDomain;
use Domain\Agent\Services\CustomDomainLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global middleware: a request to an agent's custom domain
 * ({@see AgentDomain}) reaches that agent's Hosted Chat and nothing else.
 *
 *  - `/` sends the visitor on to the chat, at `/chat/{slug}` on the same
 *    domain, with the query kept (sign-in links carry their token in it).
 *  - The agent's own `/chat/{slug or external id}/…` pages and calls pass, and
 *    so do the signed media and model logos those pages show.
 *  - Everything else is a 404: the dashboard, the admin panel and other
 *    agents never answer on a customer's domain.
 *  - `/.well-known/chatvia-domain/{token}` answers the domain's check, also
 *    before the domain is verified.
 *
 * Any other host passes untouched. Appended after TrustProxies, so the host
 * and the scheme are the ones the visitor used.
 */
final readonly class ServeCustomDomain
{
    private const string CHECK_PATH = '.well-known/chatvia-domain/';

    /** Paths outside `/chat` that a Hosted Chat page loads. */
    private const array CHAT_ASSET_PATHS = ['media/*', 'model-logos/*'];

    public function __construct(private CustomDomainLookup $domains) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        if (! $this->domains->isSaved($host)) {
            return $next($request);
        }

        if ($request->is(self::CHECK_PATH.'*')) {
            return $this->answerCheck($request, $host);
        }

        $agentId = $this->domains->activeAgentIdFor($host);
        $agent = $agentId === null ? null : Agent::query()->find($agentId, ['id', 'slug', 'external_id']);

        abort_unless($agent instanceof Agent, Response::HTTP_NOT_FOUND);

        if ($request->path() === '/') {
            $query = $request->getQueryString();

            return redirect()->to('/chat/'.rawurlencode($agent->slug ?? $agent->external_id).($query === null ? '' : "?{$query}"));
        }

        abort_unless($this->isOwnChat($request, $agent) || $request->is(...self::CHAT_ASSET_PATHS), Response::HTTP_NOT_FOUND);

        return $next($request);
    }

    private function answerCheck(Request $request, string $host): Response
    {
        $token = (string) $this->domains->verificationTokenFor($host);

        abort_unless(hash_equals($token, Str::after($request->path(), self::CHECK_PATH)), Response::HTTP_NOT_FOUND);

        return response($token, Response::HTTP_OK, ['Content-Type' => 'text/plain', 'Cache-Control' => 'no-store']);
    }

    private function isOwnChat(Request $request, Agent $agent): bool
    {
        return collect([$agent->slug, $agent->external_id])
            ->filter()
            ->contains(fn (string $segment): bool => $request->is("chat/{$segment}", "chat/{$segment}/*"));
    }
}
