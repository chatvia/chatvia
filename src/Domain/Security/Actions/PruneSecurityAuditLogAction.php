<?php

declare(strict_types=1);

namespace Domain\Security\Actions;

use Domain\Security\Models\SecurityAuditLog;
use Domain\Security\Models\WorkspaceSecurityPolicy;

/**
 * Deletes security audit rows past their retention window. A workspace that
 * set its own (shorter) audit retention uses it — never below the configured
 * floor (`chatvia.security_audit.retention_floor_days`), a mandated minimum;
 * every other workspace uses the default window.
 */
class PruneSecurityAuditLogAction
{
    /**
     * @return int how many rows were deleted
     */
    public function execute(int $defaultDays): int
    {
        $floor = (int) config('chatvia.security_audit.retention_floor_days', 30);

        $overrides = WorkspaceSecurityPolicy::query()
            ->whereNotNull('retention_audit_days')
            ->pluck('retention_audit_days', 'workspace_id');

        $deleted = 0;

        foreach ($overrides as $workspaceId => $days) {
            $deleted += SecurityAuditLog::query()
                ->where('workspace_id', $workspaceId)
                ->where('created_at', '<', now()->subDays(max($floor, (int) $days)))
                ->delete();
        }

        $deleted += SecurityAuditLog::query()
            ->whereNotIn('workspace_id', $overrides->keys())
            ->where('created_at', '<', now()->subDays($defaultDays))
            ->delete();

        return $deleted;
    }
}
