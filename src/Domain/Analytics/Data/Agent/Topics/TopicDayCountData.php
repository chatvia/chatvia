<?php

namespace Domain\Analytics\Data\Agent\Topics;

/**
 * Conversations about one topic on one day (YYYY-MM-DD). The topic name is
 * null when the topic was deleted since.
 */
final readonly class TopicDayCountData
{
    public function __construct(
        public string $date,
        public int $topicId,
        public ?string $topicName,
        public int $count,
    ) {}
}
