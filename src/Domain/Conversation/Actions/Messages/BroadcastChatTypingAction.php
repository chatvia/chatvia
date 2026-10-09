<?php

namespace Domain\Conversation\Actions\Messages;

use Domain\Conversation\Data\Messages\BroadcastChatTypingData;
use Domain\Conversation\Events\TypingStarted;

/**
 * Tells the other side of a handed-off conversation that someone is typing.
 */
class BroadcastChatTypingAction
{
    public function execute(BroadcastChatTypingData $data): void
    {
        TypingStarted::dispatch(
            $data->conversationId,
            $data->isTyping,
            $data->typerType,
            $data->typerName,
            $data->typerId,
        );
    }
}
