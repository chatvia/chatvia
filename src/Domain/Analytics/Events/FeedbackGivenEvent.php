<?php

namespace Domain\Analytics\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;

class FeedbackGivenEvent
{
    use Dispatchable;

    /**
     * @param  'positive'|'negative'|null  $priorFeedback
     * @param  'positive'|'negative'|null  $newFeedback
     */
    public function __construct(
        public readonly int $agentId,
        public readonly string $conversationExternalId,
        public readonly string $messageExternalId,
        public readonly ?string $priorFeedback,
        public readonly ?string $newFeedback,
        public readonly ?string $llmExternalName,
        public readonly ?string $countryCode,
        public readonly DateTimeInterface $occurredAt,
    ) {}
}
