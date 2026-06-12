<?php

namespace Domain\Conversation\Listeners;

use Domain\Agent\Notifications\AgentRateLimitNotification;
use Domain\Conversation\Events\AgentRateLimitExceeded;
use Illuminate\Support\Facades\Cache;

/**
 * Emails the workspace owner when their agent trips its chat rate limit.
 *
 * The event fires on every blocked message, so the email is throttled to at
 * most once per hour per agent — a sustained spike shouldn't bury the owner's
 * inbox. The notification itself is queued; this listener stays on the request
 * thread doing only a single atomic cache check.
 */
class SendAgentRateLimitNotification
{
    public function handle(AgentRateLimitExceeded $event): void
    {
        $owner = $event->agent->user;

        if ($owner === null) {
            return;
        }

        // Cache::add is atomic — it returns true only for the first hit in the
        // window, so concurrent blocked messages still send exactly one email.
        if (! Cache::add('agent-rate-limit-email:'.$event->agent->id, true, now()->addHour())) {
            return;
        }

        $owner->notify(new AgentRateLimitNotification($event->agent, $event->limitPerMinute));
    }
}
