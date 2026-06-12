<?php

namespace Domain\Analytics\Data\Chat;

use Spatie\LaravelData\Data;

/**
 * One turn of the owner-facing "Ask your data" chat. The transcript is held by
 * the browser and replayed on each request (the chat is ephemeral — nothing is
 * persisted server-side), so `history` carries the prior turns for context.
 */
class AnalyticsChatRequestData extends Data
{
    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function __construct(
        public string $message,
        public array $history = [],
        public ?string $userMessageId = null,
    ) {}
}
