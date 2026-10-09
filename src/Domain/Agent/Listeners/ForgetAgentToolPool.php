<?php

declare(strict_types=1);

namespace Domain\Agent\Listeners;

use Domain\Agent\Events\AgentToolsChanged;
use Domain\Integration\Tool\Registry\ToolRegistry;

/**
 * Drops an agent's cached tool pool when its attached functions or
 * connectors change, so the very next chat turn sees the new set. Runs in the
 * same request (not queued) for that reason.
 */
final readonly class ForgetAgentToolPool
{
    public function __construct(
        private ToolRegistry $registry,
    ) {}

    public function handle(AgentToolsChanged $event): void
    {
        $this->registry->forget($event->agentId);
    }
}
