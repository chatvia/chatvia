<?php

namespace App\Widget;

use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Models\ChatSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Pre-request widget gate.
 *
 * Two responsibilities:
 *
 *   1. `verifyByAgentExternalId` — Origin/Referer/Host allow-list check. This
 *      is a SOFT control: a non-browser client (curl, headless script) can set
 *      Origin/Referer to anything. It stops *unintentional* embedding from a
 *      site the customer hasn't authorised; it does NOT stop scripted abuse.
 *      Every sensitive operation must additionally be agent-scoped (see #2)
 *      and rate-limited (see routes/widget.php throttle middleware).
 *
 *   2. `verifySessionExternalId` / `verifyThreadId` — resolve a session/thread
 *      by external id AND confirm it belongs to the agent on the request.
 *      Calling these without an agent argument is reserved for the legacy
 *      paths we still need to migrate and emits no scoping at all; new code
 *      must pass the agent.
 */
class WidgetVerification
{
    public function verifyByAgentExternalId(string $externalId, Request $request): bool
    {
        /** @var Agent|null $agent */
        $agent = Agent::where('external_id', $externalId)->first();

        if (! $agent) {
            return false;
        }

        $requestingDomain = $this->getRequestingDomain($request);

        if (! $requestingDomain) {
            return false;
        }

        $widgetSettings = $agent->settings('widget');

        // Dev hosts are only ever in the allow-list while running locally.
        // In production a spoofed `Origin: localhost` request must NOT pass —
        // it would bypass the entire allow-list otherwise.
        $alwaysAllowed = app()->environment('local')
            ? ['localhost', 'chatvia.test', 'chatvia.ai']
            : ['chatvia.ai'];

        $allowedSites = array_merge($widgetSettings['sites'], $alwaysAllowed);

        return $this->isDomainAllowed($requestingDomain, $allowedSites);
    }

    /**
     * Resolve a chat session by its external id, optionally scoped to a
     * specific agent. New code must always pass the agent — without it a
     * caller can submit a session id from agent A while claiming to act on
     * agent B (cross-agent session hijacking).
     */
    public function verifySessionExternalId(?string $sessionId, ?Agent $agent = null): ?ChatSession
    {
        if (! $sessionId) {
            return null;
        }

        $query = ChatSession::where('external_id', $sessionId);

        if ($agent !== null) {
            $query->where('agent_id', $agent->id);
        }

        return $query->first();
    }

    /**
     * Resolve a chat conversation by its external id, optionally scoped to a
     * specific agent. Same warning as {@see verifySessionExternalId} — new
     * code must pass the agent.
     */
    public function verifyThreadId(?string $threadId, ?Agent $agent = null): ?ChatConversation
    {
        if (! $threadId) {
            return null;
        }

        $query = ChatConversation::where('external_id', $threadId);

        if ($agent !== null) {
            $query->whereHas('session', fn ($q) => $q->where('agent_id', $agent->id));
        }

        return $query->first();
    }

    public function getRequestingDomain(Request $request): ?string
    {
        $origin = $request->header('origin');

        if ($origin) {
            $parsed = parse_url($origin);

            return $parsed['host'] ?? null;
        }

        $referer = $request->header('referer');
        if ($referer) {
            $parsed = parse_url($referer);

            return $parsed['host'] ?? null;
        }

        return $request->getHost();
    }

    /**
     * Domain allow-list check. Supports an exact host, a `www.` flip, and a
     * `*.example.com` wildcard. Note: the wildcard matches arbitrarily-deep
     * subdomains — `evil.sub.example.com` passes a `*.example.com` rule. The
     * customer's widget settings page documents this.
     */
    public function isDomainAllowed(string $domain, array $allowedSites): bool
    {
        if (in_array($domain, $allowedSites, true)) {
            return true;
        }

        $domainWithoutWww = preg_replace('/^www\./', '', $domain);
        $domainWithWww = Str::startsWith($domain, 'www.') ? $domain : 'www.'.$domain;

        foreach ($allowedSites as $allowedSite) {
            $allowedWithoutWww = preg_replace('/^www\./', '', $allowedSite);
            $allowedWithWww = Str::startsWith($allowedSite, 'www.') ? $allowedSite : 'www.'.$allowedSite;

            if ($domainWithoutWww === $allowedWithoutWww ||
                $domain === $allowedWithWww ||
                $domainWithWww === $allowedSite) {
                return true;
            }

            if (Str::startsWith($allowedSite, '*.')) {
                $baseDomain = Str::substr($allowedSite, 2);
                if ($domainWithoutWww === $baseDomain ||
                    Str::endsWith($domainWithoutWww, '.'.$baseDomain) ||
                    Str::endsWith($domain, '.'.$baseDomain)) {
                    return true;
                }
            }
        }

        return false;
    }
}
