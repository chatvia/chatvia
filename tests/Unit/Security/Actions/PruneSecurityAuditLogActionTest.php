<?php

use App\Identity\Workspace\Models\User;
use Domain\Security\Actions\PruneSecurityAuditLogAction;
use Domain\Security\Models\SecurityAuditLog;
use Domain\Security\Models\WorkspaceSecurityPolicy;

function auditRow(User $workspace, int $daysAgo): void
{
    SecurityAuditLog::query()->forceCreate([
        'workspace_id' => $workspace->workspace()->id,
        'action' => 'auth.login',
        'created_at' => now()->subDays($daysAgo),
    ]);
}

it('uses each workspace override, clamped to the floor, and the default for the rest', function (): void {
    config(['chatvia.security_audit.retention_floor_days' => 30]);

    $shortened = User::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($shortened->workspace()->id)->update(['retention_audit_days' => 60]);
    auditRow($shortened, 90);  // past its 60-day override → deleted
    auditRow($shortened, 45);  // inside it → kept

    $belowFloor = User::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($belowFloor->workspace()->id)->update(['retention_audit_days' => 5]);
    auditRow($belowFloor, 20); // the 5-day override is clamped up to the 30-day floor → kept

    $default = User::factory()->create();
    auditRow($default, 400);   // past the 365-day default → deleted
    auditRow($default, 100);   // kept

    $deleted = app(PruneSecurityAuditLogAction::class)->execute(365);

    expect($deleted)->toBe(2)
        ->and(SecurityAuditLog::query()->where('workspace_id', $shortened->workspace()->id)->count())->toBe(1)
        ->and(SecurityAuditLog::query()->where('workspace_id', $belowFloor->workspace()->id)->count())->toBe(1)
        ->and(SecurityAuditLog::query()->where('workspace_id', $default->workspace()->id)->count())->toBe(1);
});

it('deletes in batches, for overriding workspaces and the rest alike', function (): void {
    config(['chatvia.security_audit.retention_floor_days' => 30]);

    $shortened = User::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($shortened->workspace()->id)->update(['retention_audit_days' => 60]);
    auditRow($shortened, 90);
    auditRow($shortened, 95);
    auditRow($shortened, 45);

    $default = User::factory()->create();
    auditRow($default, 400);
    auditRow($default, 500);
    auditRow($default, 100);

    expect(app(PruneSecurityAuditLogAction::class)->execute(365, batchSize: 1))->toBe(4)
        ->and(SecurityAuditLog::query()->where('workspace_id', $shortened->workspace()->id)->count())->toBe(1)
        ->and(SecurityAuditLog::query()->where('workspace_id', $default->workspace()->id)->count())->toBe(1);
});
