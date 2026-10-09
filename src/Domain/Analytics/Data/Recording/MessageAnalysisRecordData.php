<?php

namespace Domain\Analytics\Data\Recording;

use DateTimeInterface;
use Domain\Analytics\Events\MessageAnalyzed;
use Spatie\LaravelData\Data;

/**
 * The sentiment and tone of one visitor message to add to the daily rollups.
 * A null dimension is not recorded (the plan or the agent has it switched off).
 * Built from a {@see MessageAnalyzed} event.
 */
class MessageAnalysisRecordData extends Data
{
    public function __construct(
        public int $agentId,
        public int $workspaceId,
        public ?string $sentiment,
        public ?string $tone,
        public DateTimeInterface $occurredAt,
    ) {}

    public static function fromEvent(MessageAnalyzed $event): self
    {
        return new self(
            agentId: $event->agentId,
            workspaceId: $event->workspaceId,
            sentiment: $event->sentiment,
            tone: $event->tone,
            occurredAt: $event->occurredAt,
        );
    }
}
