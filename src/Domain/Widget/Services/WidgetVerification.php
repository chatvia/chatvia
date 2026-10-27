<?php

namespace Domain\Widget\Services;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Support\Http\SafeUrl;

/**
 * The checks every widget API call relies on. This is one of the few places
 * where the domain layer reads the HTTP request on purpose: it is the
 * boundary that turns the widget's request into trusted domain facts.
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
     * On a PROTECTED agent pass the signed-in `$viewer` too, so the session must
     * also belong to that chatter (or staff preview). Without it the session id would work like a
     * bearer token, and one chatter could read another's threads by replaying
     * their id.
     */
    public function verifySessionExternalId(?string $sessionId, Agent $agent, ?ChatViewerData $viewer = null): ?Session
    {
        // External ids are UUIDs; anything else can never match (and would
        // make Postgres reject the query).
        if (! $sessionId || ! Str::isUuid($sessionId)) {
            return null;
        }

        return Session::query()
            ->where('external_id', $sessionId)
            ->where('agent_id', $agent->id)
            ->when($viewer instanceof ChatViewerData, fn (Builder $query) => $query->where($viewer->sessionColumn(), $viewer->id))
            ->first();
    }

    /**
     * Whether the session id is already taken by someone else's session:
     * another agent's, or on a protected agent another signed-in account's
     * (pass `$viewer`). A visitor's widget picks a fresh id on its first visit,
     * so only a replayed id can be someone else's, and nothing may be read or
     * started in it. An id no session has yet is free.
     */
    public function isSessionOfSomeoneElse(?string $sessionId, Agent $agent, ?ChatViewerData $viewer = null): bool
    {
        if (! $sessionId || ! Str::isUuid($sessionId)) {
            return false;
        }

        $session = Session::query()->where('external_id', $sessionId)->first();

        if (! $session instanceof Session) {
            return false;
        }

        return $session->agent_id !== $agent->id
            || ($viewer instanceof ChatViewerData && $session->getAttribute($viewer->sessionColumn()) !== $viewer->id);
    }

    /**
     * Resolve a chat conversation by its external id, with the same agent (and
     * protected-agent chatter) scoping as {@see verifySessionExternalId()}.
     *
     * Threads the visitor cleared never resolve: once cleared, a conversation
     * must not come back on the widget (message list, boot restore, form
     * submit) even when its id is replayed. The row itself stays for the
     * customer's chat log, which reads conversations through its own query.
     * The session-scoped endpoints apply the same rule through
     * {@see FindWidgetConversationAction}.
     */
    public function verifyConversationId(?string $conversationId, Agent $agent, ?ChatViewerData $viewer = null): ?Conversation
    {
        if (! $conversationId || ! Str::isUuid($conversationId)) {
            return null;
        }

        return Conversation::query()
            ->where('external_id', $conversationId)
            ->reachableByWidgetVisitor($agent->id, $viewer)
            ->first();
    }

    /**
     * Host of the page that made the request: from the Origin header, else the
     * Referer header, else the host the request was sent to.
     */
    private function requestingHost(Request $request): ?string
    {
        foreach (['origin', 'referer'] as $header) {
            $value = $request->headers->get($header);

            if (filled($value)) {
                return SafeUrl::parse($value)?->host() ?: null;
            }
        }

        return $request->getHost();
    }

    /**
     * Hosts every agent's widget may load on, on top of the customer's own
     * list: the install's own APP_URL host (where the dashboard playground and
     * the hosted chat embed the widget, `chatvia.widget.always_allowed_origins`)
     * plus the extra hosts set in the admin panel
     * (`chatvia.widget.extra_allowed_origins`). No vendor domain is built in, so
     * a self-hosted install never trusts someone else's domain. `localhost` is
     * added only in the local environment: in production a faked
     * `Origin: localhost` would otherwise pass every allow-list.
     *
     * @return list<string>
     */
    private function alwaysAllowedHosts(): array
    {
        $hosts = collect(['always_allowed_origins', 'extra_allowed_origins'])
            ->flatMap(static function (string $key): array {
                $value = config("chatvia.widget.{$key}", []);

                return is_array($value) ? $value : Str::of((string) $value)->explode(',')->all();
            })
            ->map(static fn (mixed $host): string => Str::lower(Str::trim((string) $host)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (App::isLocal()) {
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
        if (collect($allowedSites)->containsStrict($host)) {
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
                    || Str::endsWith($hostWithoutWww, ".{$baseDomain}")
                    || Str::endsWith($host, ".{$baseDomain}")) {
                    return true;
                }
            }
        }

        return false;
    }

    private function withoutWww(string $host): string
    {
        return Str::chopStart($host, 'www.');
    }

    private function withWww(string $host): string
    {
        return Str::startsWith($host, 'www.') ? $host : "www.{$host}";
    }
}
