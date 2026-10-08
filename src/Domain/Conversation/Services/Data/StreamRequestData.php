<?php

namespace Domain\Conversation\Services\Data;

use Domain\Agent\Models\Agent;
use Spatie\LaravelData\Data;

class StreamRequestData extends Data
{
    public function __construct(
        public Agent $agent,
        public StreamRequestMessageData $message,
        public string $externalSessionId,
        public string $conversationId,
        // The authenticated Chatter (widget end-user) sending this turn, or null
        // for anonymous visitors and dashboard/chat senders. Stamps Session.user_id,
        // drives the per-chatter message limit, and records analytics chatter_user_id.
        public ?int $chatterUserId = null,
        // The signed-in chatter's display name (users.name), resolved up front so
        // buildSystemPrompt() can address them by name without a mid-stream query.
        // Only set alongside chatterUserId; null for anonymous/owner/employee senders.
        public ?string $chatterName = null,
        // The signed-in chatter's opaque public id (users.external_id), stamped
        // on outbound tool calls as X-Chatvia-Chatter-Id so a customer's MCP or
        // webhook can tell WHICH end-user is talking — the same id they got from
        // POST /api/v1/chatters. Null for anonymous/owner/employee senders.
        public ?string $chatterExternalId = null,
        // The authenticated user who caused this turn — ANY type (chatter, owner,
        // teammate), or null for an anonymous visitor. Unlike chatterUserId this is
        // not filtered to chatters and never affects billing/prompts; it exists so
        // the governance audit attributes the action to whoever is logged in.
        public ?int $actorUserId = null,
        public ?string $actorName = null,
        // In Chat, when the user creates a new conversation, their message will be saved with the conversation ID.
        public bool $saveUserMessageImmediately = false,
        public bool $streamSources = false,
        public bool $streamLanguage = false,
        public ?string $source = null,
        public ?string $ipAddress = null,
        // The visitor's ISO-3166 alpha-2 country (lowercase), resolved at request
        // time via Request::country() — Cloudflare's CF-IPCountry behind the proxy,
        // an IP geo lookup otherwise. Captured here because the queued location
        // listener runs without the request and can't see CF-IPCountry; geo-locating
        // the stored IP there would mislabel CF-proxied traffic as the edge PoP.
        public ?string $country = null,
        public ?string $userAgent = null,
        public ?string $referrerUrl = null,
        public ?string $pageUrl = null,
        public ?string $locale = null,
        public ?string $requestId = null,
        // Per-run switch for the input + RAG-source guardrails, on top of the
        // global and per-agent toggles. Live chat leaves it on; an eval run
        // turns it off for itself only, without touching global config that
        // other code in the same worker reads.
        public bool $guardrailsEnabled = true,
    ) {}
}
