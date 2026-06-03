<?php

namespace Domain\Conversation\Actions\Conversations;

use Domain\Conversation\Models\Conversation;

class RecordConversationActivityAction
{
    public function execute(string $conversationExternalId): void
    {
        Conversation::where('external_id', $conversationExternalId)
            ->update(['last_activity_at' => now()]);
    }
}
