<?php

namespace Domain\Analytics\Data\Account\Activity;

/**
 * One agent's traffic over a window.
 */
final readonly class AgentActivityData
{
    public function __construct(
        public int $agentId,
        public string $name,
        public int $messages,
        public int $conversations,
    ) {}
}
