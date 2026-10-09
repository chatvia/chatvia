<?php

declare(strict_types=1);

namespace Domain\Analytics\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A chat message (visitor or AI) was stored. Recorded into the analytics
 * tables by the queued RecordAnalytics subscriber. Queued payloads hold this
 * class, so its name and fields must stay compatible.
 */
class MessageSent
{
    use Dispatchable;

    /**
     * @param  'user'|'assistant'  $role
     */
    public function __construct(
        public readonly int $agentId,
        // The workspace owner this event belongs to (denormalized from
        // agent.user_id; survives agent deletion).
        public readonly int $workspaceId,
        // The chatter (end-user) who sent the message, or null for anonymous
        // widget visitors. The "who" behind the event.
        public readonly ?int $chatterUserId,
        public readonly string $conversationExternalId,
        public readonly string $messageExternalId,
        public readonly string $sessionExternalId,
        public readonly string $role,
        public readonly ?string $llmExternalName,
        public readonly ?string $countryCode,
        public readonly ?int $responseTimeMs,
        public readonly ?int $promptTokens,
        public readonly ?int $completionTokens,
        public readonly DateTimeInterface $occurredAt,
    ) {}
}
