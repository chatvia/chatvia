<?php

namespace App\Console\Commands\Prune;

use Domain\Security\Actions\PruneSecurityAuditLogAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled retention for the security audit log (see
 * {@see PruneSecurityAuditLogAction}).
 */
#[Description('Delete security audit rows (security_audit_logs) older than the retention window, honouring per-workspace overrides clamped to the config floor.')]
#[Signature('chatvia:prune:security-audit-log {--days= : Default retention in days; defaults to config(chatvia.security_audit.retention_days)}')]
class PruneSecurityAuditLog extends Command
{
    public function handle(PruneSecurityAuditLogAction $prune): int
    {
        $default = $this->option('days') !== null
            ? max(0, (int) $this->option('days'))
            : (int) config('chatvia.security_audit.retention_days', 365);

        $deleted = $prune->execute($default);
        $floor = (int) config('chatvia.security_audit.retention_floor_days', 30);

        $this->info("Pruned {$deleted} security-audit row(s) (default {$default}d, floor {$floor}d).");

        return self::SUCCESS;
    }
}
