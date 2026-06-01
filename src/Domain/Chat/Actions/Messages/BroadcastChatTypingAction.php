<?php

namespace Domain\Chat\Actions\Messages;

use Domain\Chat\Data\Messages\BroadcastChatTypingData;
use Domain\Chat\Events\ChatTypingStarted;

class BroadcastChatTypingAction
{
    public function execute(BroadcastChatTypingData $data): void
    {
        ChatTypingStarted::dispatch(
            $data->conversationId,
            $data->isTyping,
            $data->typerType,
            $data->typerName,
            $data->typerId,
        );
    }
}
