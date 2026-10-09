<?php

namespace Domain\Analytics\Data\Recording;

use DateTimeInterface;
use Domain\Analytics\Events\ConversationTopicAssigned;
use Spatie\LaravelData\Data;

/**
 * A topic newly assigned to a conversation, to add to the daily topic rollup
 * on the conversation's own day. Built from a {@see ConversationTopicAssigned}
 * event.
 */
class TopicAssignmentRecordData extends Data
{
    public function __construct(
        public int $agentId,
        public int $workspaceId,
        public int $topicId,
        public DateTimeInterface $occurredAt,
    ) {}

    public static function fromEvent(ConversationTopicAssigned $event): self
    {
        return new self(
            agentId: $event->agentId,
            workspaceId: $event->workspaceId,
            topicId: $event->topicId,
            occurredAt: $event->occurredAt,
        );
    }
}
