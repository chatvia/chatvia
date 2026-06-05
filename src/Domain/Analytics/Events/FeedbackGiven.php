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
        public readonly int $userId,
        public readonly string $conversationExternalId,
        public readonly string $messageExternalId,
        public readonly ?string $feedback,
        public readonly ?string $llmExternalName,
        public readonly ?string $countryCode,
        public readonly DateTimeInterface $occurredAt,
    ) {}
}
