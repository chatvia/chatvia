<?php

namespace Domain\Chat\Actions;

use Domain\Chat\Data\BroadcastChatTypingData;
use Domain\Chat\Events\ChatTypingEvent;

class BroadcastChatTypingAction
{
    public function execute(BroadcastChatTypingData $data): void
    {
        ChatTypingEvent::dispatch(
            $data->conversationId,
            $data->isTyping,
            $data->typerType,
            $data->typerName,
            $data->typerId,
        );
    }
}
