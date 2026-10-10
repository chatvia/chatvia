<?php

use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Models\UserQuota;

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
    $starter = BillingPlan::query()->where('key', 'starter')->firstOrFail();
    $limits = $starter->quota_limits;
    $limits['fixed']['workspace_users_limit'] = 5;
    $limits['fixed']['teams_limit'] = 2;
    updateBillingPlan('starter', ['is_default' => true, 'quota_limits' => $limits]);

    $user = User::factory()->create(['type' => UserType::Workspace]);
    $user->quota()->delete();
    $user->refresh();

    // No workspace users / teams created yet → full default allotment remains.
    expect($user->remainingWorkspaceUsersQuota())->toBe(5)
        ->and($user->remainingTeamsQuota())->toBe(2)
        ->and($user->canAddWorkspaceUser())->toBeTrue()
        ->and($user->canCreateTeam())->toBeTrue();
});

/**
 * The Members add-on mirrors its subscription quantity onto
 * `extra_members_limit`, which must lift the shared workspace seat cap that
 * both team-member invites and chatter creation gate against.
 */
it('lets the Members add-on lift the workspace seat limit past the plan base', function (): void {
    $user = User::factory()->create(['type' => UserType::Workspace]);

    // Registration no longer auto-provisions a user_quotas row (the trial
    // checkout webhook does), so seed one explicitly before exhausting the
    // base plan: no seats free beyond those already in use.
    UserQuota::firstOrCreate(
        ['user_id' => $user->id],
        planFixedLimits(),
    );

    $user->quota()->update([
        'workspace_users_limit' => $user->workspaceUsersCount(),
        'extra_members_limit' => 0,
    ]);

    expect($user->fresh()->canAddWorkspaceUser())->toBeFalse();

    // Buying 2 Members seats reopens capacity.
    $user->quota()->update(['extra_members_limit' => 2]);
    $fresh = $user->fresh();

    expect($fresh->quota->totalWorkspaceUsersLimit())->toBe($fresh->workspaceUsersCount() + 2)
        ->and($fresh->canAddWorkspaceUser())->toBeTrue()
        ->and($fresh->remainingWorkspaceUsersQuota())->toBe(2);
});
