<?php

namespace Domain\Analytics\Data\Agent\Topics;

/**
 * Conversations about one topic over a whole range. The topic name is null
 * when the topic was deleted since.
 */
final readonly class TopicTotalData
{
    public function __construct(
        public int $topicId,
        public ?string $topicName,
        public int $count,
    ) {}
}
