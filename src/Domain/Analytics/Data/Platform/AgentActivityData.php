<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Platform;

use Carbon\CarbonImmutable;

/**
 * One agent's traffic over a window and the last hour it had any traffic at
 * all, for the admin panel's list of a workspace's agents.
 */
final readonly class AgentActivityData
{
    public function __construct(
        public int $agentId,
        public int $messages,
        public int $conversations,
        public ?CarbonImmutable $lastActiveAt,
    ) {}
}
