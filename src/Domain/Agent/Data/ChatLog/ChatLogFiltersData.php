<?php

namespace Domain\Agent\Data\ChatLog;

use Spatie\LaravelData\Data;

/**
 * The chat-log list's page and filters, as the dashboard sends them. Every
 * filter is optional; an empty one doesn't narrow the list.
 */
class ChatLogFiltersData extends Data
{
    public function __construct(
        public int $page = 1,
        public ?string $topic = null,
        public ?string $sentiment = null,
        public ?string $tone = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?string $country = null,
        public ?string $confidenceScore = null,
        public ?string $feedback = null,
        public ?string $source = null,
        public bool $onlineOnly = false,
    ) {}
}
