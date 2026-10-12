<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Platform;

/**
 * One agent's traffic over a window, with the workspace it belongs to. Names
 * are null when the agent or workspace has been deleted since.
 */
final readonly class TopAgentData
{
    public function __construct(
        public int $agentId,
        public ?string $name,
        public int $workspaceId,
        public ?string $workspaceName,
        public int $messages,
        public int $conversations,
    ) {}
}
