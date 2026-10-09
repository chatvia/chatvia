<?php

namespace Domain\Analytics\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A conversation was newly assigned one of the agent's topics, bucketed on
 * the conversation's own creation time.
 */
class ConversationTopicAssigned
{
    use Dispatchable;

    public function __construct(
        public readonly int $agentId,
        public readonly int $workspaceId,
        public readonly int $topicId,
        public readonly DateTimeInterface $occurredAt,
    ) {}
}
