<?php

use App\Admin\Settings\BrandSettings;
use App\Identity\Workspace\Models\User;
use Domain\Security\Actions\PruneSecurityAuditLogAction;
use Domain\Security\Models\SecurityAuditLog;
use Domain\Security\Models\WorkspaceSecurityPolicy;

/**
 * Security audit rows past their retention go: each workspace's own window
 * (never below the floor), the default for the rest, and the default for
 * every workspace without an Enterprise license.
 */
/** An audit row of the owner's workspace, $daysAgo days old. */
function psaRow(User $owner, int $daysAgo): void
{
    SecurityAuditLog::query()->forceCreate([
        'workspace_id' => $owner->workspace()->id,
        'action' => 'auth.login',
        'created_at' => now()->subDays($daysAgo),
    ]);
}

beforeEach(fn () => withEnterpriseLicense());

it('uses each workspace override, clamped to the floor, and the default for the rest', function (): void {
    config(['chatvia.security_audit.retention_floor_days' => 30]);

    $shortened = User::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($shortened->workspace()->id)->update(['retention_audit_days' => 60]);
    psaRow($shortened, 90);
    psaRow($shortened, 45);

    $belowFloor = User::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($belowFloor->workspace()->id)->update(['retention_audit_days' => 5]);
    psaRow($belowFloor, 20);

    $default = User::factory()->create();
    psaRow($default, 400);
    psaRow($default, 100);

    $deleted = app(PruneSecurityAuditLogAction::class)->execute(365);

    expect($deleted)->toBe(2)
        ->and(SecurityAuditLog::query()->where('workspace_id', $shortened->workspace()->id)->count())->toBe(1)
        ->and(SecurityAuditLog::query()->where('workspace_id', $belowFloor->workspace()->id)->count())->toBe(1)
        ->and(SecurityAuditLog::query()->where('workspace_id', $default->workspace()->id)->count())->toBe(1);
});

it('uses the default window for every workspace without an Enterprise license', function (): void {
    $shortened = User::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($shortened->workspace()->id)->update(['retention_audit_days' => 60]);
    psaRow($shortened, 90);
    psaRow($shortened, 400);
    saveSettings(BrandSettings::class, ['license_key' => null]);

    expect(app(PruneSecurityAuditLogAction::class)->execute(defaultDays: 365))->toBe(1)
        ->and(SecurityAuditLog::query()->count())->toBe(1);
});
