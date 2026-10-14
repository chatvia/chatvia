<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Turn;

use Domain\Agent\Models\Agent;
use Spatie\LaravelData\Data;

/**
 * Everything one chat turn needs to know about the request that started it:
 * the agent, the visitor's message and model, the session and conversation,
 * who is talking (chatter and audit actor), the request facts stored on the
 * session, and per-turn switches. Built by the HTTP layer for the web chat
 * and the widget, and directly by the messaging channels and evals.
 */
class StreamRequestData extends Data
{
    public function __construct(
        public Agent $agent,
        public StreamRequestMessageData $message,
        public string $externalSessionId,
        public string $conversationId,
        // The signed-in chatter (chatters.id) sending this turn, or null for
        // anonymous visitors and staff. Stamps Session.chatter_id, drives the
        // per-chatter message limit, and records analytics chatter_id.
        public ?int $chatterId = null,
        // The signed-in chatter's display name, resolved up front so
        // buildSystemPrompt() can address them by name without a mid-stream query.
        // Only set alongside chatterId; null for anonymous/staff senders.
        public ?string $chatterName = null,
        // The signed-in chatter's opaque public id (chatters.external_id), stamped
        // on outbound tool calls as X-Chatvia-Chatter-Id so a customer's MCP or
        // webhook can tell WHICH end-user is talking — the same id they got from
        // POST /api/v1/chatters. Null for anonymous/owner/employee senders.
        public ?string $chatterExternalId = null,
        // The staff account (users.id: owner, teammate, admin previewing) who
        // caused this turn, or null for a chatter or an anonymous visitor. It
        // never affects message limits or prompts; with chatterId it lets the
        // governance audit attribute the action to whoever is signed in.
        public ?int $actorUserId = null,
        // The display name of whoever is signed in (chatter or staff), for the
        // audit trail; null for an anonymous visitor.
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
        // The turn was spoken in a voice call: its reply is marked as such
        // (`metadata.voice`) so the chat log can show it.
        public bool $voice = false,
    ) {}
}
