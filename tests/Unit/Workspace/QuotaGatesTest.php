<?php

use App\Identity\Workspace\Models\User;
use Support\Enums\User\UserType;

/**
 * The quota gates must not fatal for a brand-new account that has no
 * `user_quotas` row yet (fresh signup before the first plan/webhook lands
 * one). canAddWorkspaceUser() previously dereferenced a null `$this->quota`
 * and threw a 500 on the add-chatter / invite-team-member paths. All four
 * gates now fall back to the default package's fixed limit.
 */
it('quota gates fall back to the default package when the account has no quota row', function (): void {
    $user = User::factory()->create(['type' => UserType::Workspace]);
    $user->quota()->delete();
    $user->refresh();

    expect($user->quota)->toBeNull()
        ->and($user->canAddWorkspaceUser())->toBeBool()
        ->and($user->canCreateTeam())->toBeBool()
        ->and($user->remainingWorkspaceUsersQuota())->toBeGreaterThanOrEqual(0)
        ->and($user->remainingTeamsQuota())->toBeGreaterThanOrEqual(0);
});

it('uses the configured default-package limits as the fallback values', function (): void {
    config([
        'billing.default_package' => 'starter',
        'billing.packages.starter.quota_limits.fixed.workspace_users_limit' => 5,
        'billing.packages.starter.quota_limits.fixed.teams_limit' => 2,
    ]);

    $user = User::factory()->create(['type' => UserType::Workspace]);
    $user->quota()->delete();
    $user->refresh();

    // No workspace users / teams created yet → full default allotment remains.
    expect($user->remainingWorkspaceUsersQuota())->toBe(5)
        ->and($user->remainingTeamsQuota())->toBe(2)
        ->and($user->canAddWorkspaceUser())->toBeTrue()
        ->and($user->canCreateTeam())->toBeTrue();
});
