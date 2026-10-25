<?php

declare(strict_types=1);

namespace Domain\Agent\Data\ChatLog;

use Spatie\LaravelData\Data;

/**
 * The chat-log list's cursor and filters, as the dashboard sends them. Every
 * filter is optional; an empty one doesn't narrow the list. No cursor means
 * the first page.
 */
class ChatLogFiltersData extends Data
{
    public function __construct(
        public ?string $cursor = null,
        public ?string $topic = null,
        public ?string $sentiment = null,
        public ?string $tone = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?string $country = null,
        public ?string $confidenceScore = null,
        public ?string $feedback = null,
        public ?string $source = null,
        public ?string $unanswered = null,
        public bool $onlineOnly = false,
    ) {}
}
