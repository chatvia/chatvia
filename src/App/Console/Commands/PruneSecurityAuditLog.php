<?php

namespace App\Console\Commands;

use Domain\Security\Models\SecurityAuditLog;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Delete security audit rows (security_audit_log) older than the retention window, honouring per-workspace overrides clamped to the config floor.')]
#[Signature('app:prune-security-audit-log {--days= : Default retention in days; defaults to config(chatvia.security_audit.retention_days)}')]
class PruneSecurityAuditLog extends Command
{
    public function handle(): int
    {
        $default = $this->option('days') !== null
            ? max(0, (int) $this->option('days'))
            : (int) config('chatvia.security_audit.retention_days', 365);

        $floor = (int) config('chatvia.security_audit.retention_floor_days', 30);

        // Workspaces that shortened their audit window. Clamped up to the floor
        // so a per-workspace override can never breach a mandated minimum.
        $overrides = WorkspaceSecurityPolicy::query()
            ->whereNotNull('retention_audit_days')
            ->pluck('retention_audit_days', 'workspace_id');

        $deleted = 0;

        foreach ($overrides as $workspaceId => $days) {
            $effective = max($floor, (int) $days);

            $deleted += SecurityAuditLog::query()
                ->where('workspace_id', $workspaceId)
                ->where('created_at', '<', now()->subDays($effective))
                ->delete();
        }

        // Everyone else falls back to the config/default window.
        $deleted += SecurityAuditLog::query()
            ->whereNotIn('workspace_id', $overrides->keys())
            ->where('created_at', '<', now()->subDays($default))
            ->delete();

        $this->info("Pruned {$deleted} security-audit row(s) (default {$default}d, floor {$floor}d).");

        return self::SUCCESS;
    }
}
