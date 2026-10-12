<?php

namespace Domain\Analytics\Data\Recording;

use DateTimeInterface;
use Domain\Analytics\Events\FeedbackGiven;
use Spatie\LaravelData\Data;

/**
 * A message's current thumbs reaction to record — `positive`, `negative`, or
 * null when the reaction was cleared. Built from a {@see FeedbackGiven} event.
 */
class FeedbackRecordData extends Data
{
    public function __construct(
        public int $agentId,
        public int $workspaceId,
        public ?int $chatterId,
        public string $conversationExternalId,
        public string $messageExternalId,
        public ?string $feedback,
        public ?string $llmExternalName,
        public ?string $countryCode,
        public DateTimeInterface $occurredAt,
    ) {}

    public static function fromEvent(FeedbackGiven $event): self
    {
        return new self(
            agentId: $event->agentId,
            workspaceId: $event->workspaceId,
            chatterId: $event->chatterId,
            conversationExternalId: $event->conversationExternalId,
            messageExternalId: $event->messageExternalId,
            feedback: $event->feedback,
            llmExternalName: $event->llmExternalName,
            countryCode: $event->countryCode,
            occurredAt: $event->occurredAt,
        );
    }
}
