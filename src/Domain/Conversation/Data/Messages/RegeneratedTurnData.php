<?php

namespace Domain\Conversation\Data\Messages;

use Domain\Conversation\Actions\Messages\RegenerateMessageAction;
use Spatie\LaravelData\Data;

/**
 * The visitor message to send again after {@see RegenerateMessageAction}
 * removed it and its answer from the history.
 */
class RegeneratedTurnData extends Data
{
    public function __construct(
        public string $userMessageContent,
        public string $createdAt,
    ) {}
}
