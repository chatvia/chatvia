<?php

namespace Domain\Widget\Services;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
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
 *   2. `verifySessionExternalId` / `verifyConversationId` — resolve a session/conversation
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
     *
     * On a PROTECTED agent the caller is authenticated; pass that `$user` too so
     * the session is also bound to its owning chatter. Without it the
     * client-supplied session id acts as an unfingerprinted bearer and one
     * signed-in chatter can read another's threads by replaying their id.
     */
    public function verifySessionExternalId(?string $sessionId, ?Agent $agent = null, ?User $user = null): ?Session
    {
        if (! $sessionId) {
            return null;
        }

        $query = Session::where('external_id', $sessionId);

        if ($agent !== null) {
            $query->where('agent_id', $agent->id);
        }

        if ($user !== null) {
            $query->where('user_id', $user->id);
        }

        return $query->first();
    }

    /**
     * Resolve a chat conversation by its external id, optionally scoped to a
     * specific agent. Same warning as {@see verifySessionExternalId} — new
     * code must pass the agent, and the authenticated `$user` on protected
     * agents so the conversation is bound to its owning chatter.
     */
    public function verifyConversationId(?string $conversationId, ?Agent $agent = null, ?User $user = null): ?Conversation
    {
        if (! $conversationId) {
            return null;
        }

        // Visitor-cleared threads are hidden from the widget: once a visitor
        // clears a conversation it must not resurface on the read side (message
        // fetch, bootstrap restore, form submit) even if the id is replayed.
        // The row stays alive in the DB for the customer's chat log, which reads
        // through a separate Conversation::forAgent() query.
        $query = Conversation::where('external_id', $conversationId)
            ->whereNull('cleared_at');

        if ($agent !== null || $user !== null) {
            $query->whereHas('session', function ($q) use ($agent, $user): void {
                if ($agent !== null) {
                    $q->where('agent_id', $agent->id);
                }

                if ($user !== null) {
                    $q->where('user_id', $user->id);
                }
            });
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
