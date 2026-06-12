<?php

namespace Domain\Conversation\Events;

use Domain\Agent\Models\Agent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when an agent's per-minute chat rate limit blocks an incoming message.
 * The listener emails the workspace owner (throttled), but only when the agent
 * opted in via the `notify_owner_on_rate_limit` security setting.
 */
class AgentRateLimitExceeded
{
    use Dispatchable;

    public function __construct(
        public Agent $agent,
        public int $limitPerMinute,
    ) {}
}
