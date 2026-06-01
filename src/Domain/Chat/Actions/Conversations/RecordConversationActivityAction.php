<?php

namespace Domain\Chat\Actions\Conversations;

use Domain\Chat\Models\ChatConversation;

class RecordConversationActivityAction
{
    public function execute(string $conversationExternalId): void
    {
        ChatConversation::where('external_id', $conversationExternalId)
            ->update(['last_activity_at' => now()]);
    }
}
