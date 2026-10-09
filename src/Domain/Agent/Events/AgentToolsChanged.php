<?php

declare(strict_types=1);

namespace Domain\Agent\Events;

use Domain\Agent\Listeners\ForgetAgentToolPool;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A function or connector was attached to, or detached from, an agent. Pivot
 * writes fire no Eloquent events, so this is what tells the tool layer to drop
 * the agent's cached tool pool ({@see ForgetAgentToolPool}).
 */
final readonly class AgentToolsChanged
{
    use Dispatchable;

    public function __construct(
        public int $agentId,
    ) {}
}
