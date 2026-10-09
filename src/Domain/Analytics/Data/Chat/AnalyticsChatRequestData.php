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
     * @param  list<AnalyticsChatTurnData>  $history
     */
    public function __construct(
        public string $message,
        /** @var list<AnalyticsChatTurnData> */
        public array $history = [],
        public ?string $userMessageId = null,
    ) {}
}
