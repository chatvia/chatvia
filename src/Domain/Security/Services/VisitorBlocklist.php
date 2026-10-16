<?php

declare(strict_types=1);

namespace Domain\Security\Services;

use Domain\Security\Models\BlockedVisitor;

/**
 * Whether an anonymous visitor is blocked from a workspace's agents. Read at
 * the start of every anonymous chat turn and voice call: one indexed lookup on
 * (workspace_id, visitor_key).
 */
final readonly class VisitorBlocklist
{
    public function __construct(
        private VisitorFingerprint $fingerprint,
    ) {}

    public function blocks(int $workspaceId, ?string $ip, ?string $userAgent): bool
    {
        $key = $this->fingerprint->for($workspaceId, $ip, $userAgent);

        return $key !== null && $this->blocksKey($workspaceId, $key);
    }

    public function blocksKey(int $workspaceId, string $visitorKey): bool
    {
        return BlockedVisitor::query()
            ->where('workspace_id', $workspaceId)
            ->where('visitor_key', $visitorKey)
            ->active()
            ->exists();
    }
}
