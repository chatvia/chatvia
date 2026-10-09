<?php

namespace Domain\Conversation\Data\Retention;

use Spatie\LaravelData\Data;

/**
 * What one run of the conversation retention sweep removed.
 */
class ConversationPruneResultData extends Data
{
    public function __construct(
        /** Soft-deleted under a workspace's shorter retention override. */
        public int $overridden,
        /** Soft-deleted at their plan retention horizon (`expires_at`). */
        public int $expired,
        /** Hard-deleted after the grace window. */
        public int $purged,
        /** Anonymous sessions left with no conversation, removed. */
        public int $orphanSessions,
    ) {}
}
