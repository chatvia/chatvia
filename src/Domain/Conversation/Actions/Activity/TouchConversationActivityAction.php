<?php

namespace Domain\Conversation\Actions\Activity;

use Domain\Conversation\Models\Conversation;
use Illuminate\Database\Eloquent\Builder;

/**
 * Moves a conversation's last-activity time forward so it counts as live in
 * the operator inbox. A base-query update, so `updated_at` (the chat log's
 * sort key) is not touched. Only a visible conversation counts as live, and
 * the stamp moves at most once a minute: every open widget pings.
 */
class TouchConversationActivityAction
{
    public function execute(Conversation $conversation): void
    {
        Conversation::query()
            ->whereKey($conversation->getKey())
            ->where('is_visible', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('last_activity_at')
                ->orWhere('last_activity_at', '<', now()->subMinute()))
            ->toBase()
            ->update(['last_activity_at' => now()]);
    }
}
