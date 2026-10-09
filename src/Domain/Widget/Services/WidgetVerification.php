<?php

namespace Domain\Widget\Services;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The checks every widget API call relies on. This is one of the sanctioned
 * places where the domain reads the HTTP request (see CLAUDE.md).
 *
 *  1. Embed origin — {@see isRequestFromAllowedSite()}: the calling page must be
 *     on one of the agent's allowed sites. This is a SOFT control: a script
 *     outside a browser can send any Origin or Referer. It stops a widget from
 *     being embedded by accident on a site the customer did not allow; it does
 *     not stop scripted abuse. So every endpoint must also scope its data to
 *     the agent (check 2) and is rate limited (routes/widget.php).
 *
 *  2. Ownership — {@see verifySessionExternalId()} and
 *     {@see verifyConversationId()}: a session or conversation id sent by the
 *     widget only resolves when it belongs to the agent of the request (and,
 *     on a protected agent, to the signed-in chatter). Anything else resolves
 *     to null, so a replayed or guessed id never reaches another visitor's data.
 */
class WidgetVerification
{
    /**
     * Whether the page that made the request is on one of the agent's allowed
     * sites (Origin header, else Referer, else the request host).
     */
    public function isRequestFromAllowedSite(Agent $agent, Request $request): bool
    {
        $host = $this->requestingHost($request);

        if ($host === null) {
            return false;
        }

        $customerSites = array_map(strval(...), (array) ($agent->settings('widget')['sites'] ?? []));

        return $this->isHostAllowed($host, [...$customerSites, ...$this->alwaysAllowedHosts()]);
    }

    /**
     * Resolve a chat session by its external id, scoped to the agent of the
     * request, so a session id from agent A can never be used on agent B.
     *
     * On a PROTECTED agent pass the signed-in `$user` too, so the session must
     * also belong to that chatter. Without it the session id would work like a
     * bearer token, and one chatter could read another's threads by replaying
     * their id.
     */
    public function verifySessionExternalId(?string $sessionId, Agent $agent, ?User $user = null): ?Session
    {
        // External ids are UUIDs; anything else can never match (and would
        // make Postgres reject the query).
        if (! $sessionId || ! Str::isUuid($sessionId)) {
            return null;
        }

        return Session::query()
            ->where('external_id', $sessionId)
            ->where('agent_id', $agent->id)
            ->when($user instanceof User, fn (Builder $query) => $query->where('user_id', $user?->id))
            ->first();
    }

    /**
     * Resolve a chat conversation by its external id, with the same agent (and
     * protected-agent chatter) scoping as {@see verifySessionExternalId()}.
     *
     * Threads the visitor cleared never resolve: once cleared, a conversation
     * must not come back on the widget (message list, boot restore, form
     * submit) even when its id is replayed. The row itself stays for the
     * customer's chat log, which reads conversations through its own query.
     */
    public function verifyConversationId(?string $conversationId, Agent $agent, ?User $user = null): ?Conversation
    {
        if (! $conversationId || ! Str::isUuid($conversationId)) {
            return null;
        }

        return Conversation::query()
            ->where('external_id', $conversationId)
            ->whereNull('cleared_at')
            ->whereHas('session', function (Builder $session) use ($agent, $user): void {
                $session->where('agent_id', $agent->id);

                if ($user instanceof User) {
                    $session->where('user_id', $user->id);
                }
            })
            ->first();
    }

    /**
     * Host of the page that made the request: from the Origin header, else the
     * Referer header, else the host the request was sent to.
     */
    private function requestingHost(Request $request): ?string
    {
        foreach (['origin', 'referer'] as $header) {
            $value = $request->header($header);

            if (is_string($value) && $value !== '') {
                return parse_url($value, PHP_URL_HOST) ?: null;
            }
        }

        return $request->getHost();
    }

    /**
     * Hosts every agent's widget may load on, on top of the customer's own
     * list: the install's own APP_URL host (where the dashboard playground and
     * the hosted chat embed the widget) plus the extra hosts in
     * `chatvia.widget.always_allowed_origins`. No vendor domain is built in, so
     * a self-hosted install never trusts someone else's domain. `localhost` is
     * added only in the local environment: in production a faked
     * `Origin: localhost` would otherwise pass every allow-list.
     *
     * @return list<string>
     */
    private function alwaysAllowedHosts(): array
    {
        $configured = config('chatvia.widget.always_allowed_origins', []);

        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        $hosts = array_values(array_filter(array_map(
            static fn (mixed $host): string => strtolower(trim((string) $host)),
            (array) $configured,
        )));

        if (app()->environment('local')) {
            $hosts[] = 'localhost';
        }

        return $hosts;
    }

    /**
     * Allow-list match. An entry matches the exact host, the same host with or
     * without `www.`, and `*.example.com` matches example.com and every
     * subdomain at any depth (`evil.sub.example.com` passes `*.example.com`;
     * the widget settings page tells the customer so).
     *
     * @param  list<string>  $allowedSites
     */
    private function isHostAllowed(string $host, array $allowedSites): bool
    {
        if (in_array($host, $allowedSites, true)) {
            return true;
        }

        $hostWithoutWww = $this->withoutWww($host);
        $hostWithWww = $this->withWww($host);

        foreach ($allowedSites as $allowedSite) {
            if ($hostWithoutWww === $this->withoutWww($allowedSite)
                || $host === $this->withWww($allowedSite)
                || $hostWithWww === $allowedSite) {
                return true;
            }

            if (Str::startsWith($allowedSite, '*.')) {
                $baseDomain = Str::substr($allowedSite, 2);

                if ($hostWithoutWww === $baseDomain
                    || Str::endsWith($hostWithoutWww, '.'.$baseDomain)
                    || Str::endsWith($host, '.'.$baseDomain)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function withoutWww(string $host): string
    {
        return (string) preg_replace('/^www\./', '', $host);
    }

    private function withWww(string $host): string
    {
        return Str::startsWith($host, 'www.') ? $host : 'www.'.$host;
    }
}
