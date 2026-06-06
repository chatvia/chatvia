<?php

namespace Domain\Analytics\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;

class FeedbackGiven
{
    use Dispatchable;

    /**
     * @param  'positive'|'negative'|null  $feedback  the new reaction; null clears it
     */
    public function __construct(
        public readonly int $agentId,
        // The workspace owner this event belongs to (denormalized from
        // agent.user_id; survives agent deletion).
        public readonly int $workspaceId,
        // The chatter (end-user) who reacted, or null for anonymous visitors.
        public readonly ?int $chatterUserId,
        public readonly string $conversationExternalId,
        public readonly string $messageExternalId,
        public readonly ?string $feedback,
        public readonly ?string $llmExternalName,
        public readonly ?string $countryCode,
        public readonly DateTimeInterface $occurredAt,
    ) {}
}
