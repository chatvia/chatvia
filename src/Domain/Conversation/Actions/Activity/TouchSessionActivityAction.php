<?php

namespace Domain\Conversation\Actions\Activity;

use Domain\Conversation\Models\Session;
use Illuminate\Database\Eloquent\Builder;

/**
 * Moves a visitor session's last-activity time forward when the visitor
 * comes back (a widget load). The session twin of
 * {@see TouchConversationActivityAction}: a base-query update that leaves
 * `updated_at` alone and moves the stamp at most once a minute, so a page
 * view does not rewrite the row every time.
 */
class TouchSessionActivityAction
{
    public function execute(Session $session): void
    {
        Session::query()
            ->whereKey($session->getKey())
            ->where(fn (Builder $query) => $query
                ->whereNull('last_activity_at')
                ->orWhere('last_activity_at', '<', now()->subMinute()))
            ->toBase()
            ->update(['last_activity_at' => now()]);
    }
}
