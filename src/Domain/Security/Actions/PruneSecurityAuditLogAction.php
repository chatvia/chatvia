<?php

declare(strict_types=1);

namespace Domain\Security\Actions;

use Carbon\CarbonInterface;
use Domain\Security\Models\SecurityAuditLog;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes security audit rows past their retention window. A workspace that
 * set its own (shorter) audit retention uses it — never below the configured
 * floor (`chatvia.security_audit.retention_floor_days`), a mandated minimum;
 * every other workspace uses the default window.
 *
 * Rows go in batches, so one run never holds a huge delete. A workspace's
 * rows are found through its `(workspace_id, created_at)` index, the rest
 * through the BRIN index on `created_at`.
 */
class PruneSecurityAuditLogAction
{
    /**
     * @return int how many rows were deleted
     */
    public function execute(int $defaultDays, int $batchSize = 10000): int
    {
        $floor = (int) config('chatvia.security_audit.retention_floor_days', 30);

        $overrides = WorkspaceSecurityPolicy::query()
            ->whereNotNull('retention_audit_days')
            ->pluck('retention_audit_days', 'workspace_id');

        $deleted = 0;

        foreach ($overrides as $workspaceId => $days) {
            $deleted += $this->deleteOlder(
                now()->subDays(max($floor, (int) $days)),
                fn (Builder $query): Builder => $query->where('workspace_id', $workspaceId),
                $batchSize,
            );
        }

        // A subquery, not the id list: it stays one parameter however many
        // workspaces set an override.
        $deleted += $this->deleteOlder(
            now()->subDays($defaultDays),
            fn (Builder $query): Builder => $query->whereNotIn('workspace_id', WorkspaceSecurityPolicy::query()
                ->select('workspace_id')
                ->whereNotNull('retention_audit_days')),
            $batchSize,
        );

        return $deleted;
    }

    /**
     * Deletes the rows older than the cutoff that the scope selects, a batch
     * at a time.
     *
     * @param  callable(Builder<SecurityAuditLog>): Builder<SecurityAuditLog>  $scope
     */
    private function deleteOlder(CarbonInterface $cutoff, callable $scope, int $batchSize): int
    {
        $deleted = 0;

        do {
            $batch = SecurityAuditLog::query()
                ->whereIn('id', $scope(SecurityAuditLog::query()->select('id')->where('created_at', '<', $cutoff))->limit($batchSize))
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }
}
