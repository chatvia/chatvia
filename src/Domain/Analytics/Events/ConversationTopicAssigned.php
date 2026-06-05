<?php

namespace Domain\Analytics\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;

class ConversationTopicAssigned
{
    use Dispatchable;

    public function __construct(
        public readonly int $agentId,
        public readonly int $userId,
        public readonly int $topicId,
        public readonly DateTimeInterface $occurredAt,
    ) {}
}
