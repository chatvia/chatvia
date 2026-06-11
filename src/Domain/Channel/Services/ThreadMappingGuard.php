<?php

namespace Domain\Channel\Services;

use Domain\Conversation\Models\Conversation;

/**
 * Guards channel thread mappings (telegram_chats, slack_channels, …) against
 * retention pruning. Conversations are soft-deleted by the daily prune; the FK
 * on the mapping only fires on the later hard purge, so a mapping can point at
 * a soft-deleted conversation. Reusing it would make StoreMessageAction's
 * firstOrCreate re-insert the still-present external_id → 23505 unique
 * violation, permanently breaking the thread. Resolvers call this before
 * returning an existing mapping and re-mint the pair when it's stale.
 */
class ThreadMappingGuard
{
    public function conversationExists(string $conversationExternalId): bool
    {
        // SoftDeletes' global scope excludes trashed rows, so this is false
        // for both pruned (soft-deleted) and purged (hard-deleted) conversations.
        return Conversation::query()
            ->where('external_id', $conversationExternalId)
            ->exists();
    }
}
