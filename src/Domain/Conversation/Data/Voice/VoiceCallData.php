<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Voice;

use Domain\Conversation\Jobs\JoinVoiceCallJob;
use Spatie\LaravelData\Data;

/**
 * One live voice call, as the worker that joins it needs it: our id and the
 * provider's id of the call, who is on it (agent, session, conversation,
 * chatter or staff, surface, request facts), the model and voice, and when
 * it must end. Kept in the cache for the call's life (VoiceCallStore) and
 * carried by {@see JoinVoiceCallJob}.
 *
 * Property names are snake_case because the object is queued; renaming one
 * would break jobs already in the queue.
 */
class VoiceCallData extends Data
{
    public function __construct(
        public string $call_id,
        public string $provider_call_id,
        public int $agent_id,
        public int $workspace_id,
        public string $surface,
        public string $session_id,
        public string $conversation_id,
        public string $model,
        public string $voice,
        // Unix time the call started, and the most seconds it may last.
        public int $started_at,
        public int $max_seconds,
        public ?int $chatter_id = null,
        public ?string $chatter_name = null,
        public ?string $chatter_external_id = null,
        public ?int $actor_user_id = null,
        public ?string $actor_name = null,
        public ?string $source = null,
        public ?string $ip_address = null,
        public ?string $country = null,
        public ?string $user_agent = null,
        public ?string $referrer_url = null,
        public ?string $page_url = null,
        public ?string $locale = null,
    ) {}

    public static function forCaller(VoiceCallerData $caller, string $callId, string $providerCallId, string $model, string $voice, int $maxSeconds): self
    {
        return new self(
            call_id: $callId,
            provider_call_id: $providerCallId,
            agent_id: (int) $caller->agent->id,
            workspace_id: (int) $caller->agent->workspace_id,
            surface: $caller->surface->value,
            session_id: $caller->sessionId,
            conversation_id: $caller->conversationId,
            model: $model,
            voice: $voice,
            started_at: now()->getTimestamp(),
            max_seconds: $maxSeconds,
            chatter_id: $caller->chatterId,
            chatter_name: $caller->chatterName,
            chatter_external_id: $caller->chatterExternalId,
            actor_user_id: $caller->actorUserId,
            actor_name: $caller->actorName,
            source: $caller->source,
            ip_address: $caller->ipAddress,
            country: $caller->country,
            user_agent: $caller->userAgent,
            referrer_url: $caller->referrerUrl,
            page_url: $caller->pageUrl,
            locale: $caller->locale,
        );
    }

    /** Unix time the call must end by. */
    public function endsAt(): int
    {
        return $this->started_at + $this->max_seconds;
    }

    /** Whether this call belongs to the visitor session of this agent. */
    public function belongsTo(int $agentId, string $sessionId): bool
    {
        return $this->agent_id === $agentId && $this->session_id === $sessionId;
    }
}
