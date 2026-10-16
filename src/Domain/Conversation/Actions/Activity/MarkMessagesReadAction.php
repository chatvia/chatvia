<?php

namespace Domain\Conversation\Actions\Activity;

use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\MessageReader;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Memory\ChatMemory;

/**
 * Read receipts for a handed-off conversation: marks the other side's unread
 * messages as read — the operator's replies when the visitor reads, the
 * visitor's messages when the operator reads — in the live memory and in the
 * chat log. Returns how many messages were marked.
 */
class MarkMessagesReadAction
{
    public function __construct(
        private readonly ChatMemory $memory,
    ) {}

    public function execute(ChatScopeData $scope, MessageReader $reader): int
    {
        $memory = $this->memory->withScope($scope);
        // The cached copy, not the client view: saving the client view back
        // would strip every message's LLM-facing text from the memory.
        $messages = $memory->cachedMessages();
        $role = $reader->reads();
        $now = now();
        $markedIds = [];

        foreach ($messages as $index => $message) {
            if ($message->role === $role && $message->read_at === null) {
                $messages[$index] = $message->withReadAt($now->timestamp);
                $markedIds[] = $message->id;
            }
        }

        if ($markedIds === []) {
            return 0;
        }

        // The cached id is the external uuid, never the integer key.
        Message::query()->whereIn('external_id', $markedIds)->update(['read_at' => $now]);

        $memory->saveMessages($messages);

        return count($markedIds);
    }
}
