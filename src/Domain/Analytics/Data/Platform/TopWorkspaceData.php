<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Platform;

/**
 * One workspace's traffic over a window. Name and owner email are null when
 * the workspace has been deleted since (its analytics are kept).
 */
final readonly class TopWorkspaceData
{
    public function __construct(
        public int $workspaceId,
        public ?string $name,
        public ?string $email,
        public int $messages,
        public int $conversations,
        public int $agents,
    ) {}
}
