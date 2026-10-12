<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Retention;

use Spatie\LaravelData\Data;

/**
 * What one run of the conversation retention sweep changed.
 */
class ConversationPruneResultData extends Data
{
    public function __construct(
        /** Horizons pulled back to the install's current retention. */
        public int $clamped,
        /** Soft-deleted under a workspace's shorter retention override. */
        public int $overridden,
        /** Soft-deleted at their retention horizon (`expires_at`). */
        public int $expired,
        /** Hard-deleted after the grace window. */
        public int $purged,
        /** Anonymous sessions left with no conversation, removed. */
        public int $orphanSessions,
    ) {}
}
