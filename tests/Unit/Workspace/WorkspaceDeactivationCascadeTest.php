<?php

use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Actions\Account\ReactivateAccountAction;
use App\Identity\Workspace\Models\User;

/**
 * Deactivating a workspace is an account-wide off switch: every member it owns
 * (employees + chatters) is switched off with the owner, and reactivating the
 * workspace restores them.
 */
it('deactivates every member when the workspace is deactivated', function (): void {
    $workspace = User::factory()->create();
    $employee = User::factory()->employee($workspace)->create();
    $chatter = User::factory()->chatter($workspace)->create();

    app(DeactivateAccountAction::class)->execute($workspace, 'Terms of service violation');

    expect($workspace->fresh()->isDeactivated())->toBeTrue()
        ->and($employee->fresh()->isDeactivated())->toBeTrue()
        ->and($chatter->fresh()->isDeactivated())->toBeTrue()
        ->and($employee->fresh()->deactivation_reason)->toBe('Terms of service violation');
});

it('does not touch members of other workspaces', function (): void {
    $workspace = User::factory()->create();
    $member = User::factory()->chatter($workspace)->create();

    $otherWorkspace = User::factory()->create();
    $otherMember = User::factory()->chatter($otherWorkspace)->create();

    app(DeactivateAccountAction::class)->execute($workspace, 'abuse');

    expect($member->fresh()->isDeactivated())->toBeTrue()
        ->and($otherWorkspace->fresh()->isDeactivated())->toBeFalse()
        ->and($otherMember->fresh()->isDeactivated())->toBeFalse();
});

it('restores members when the workspace is reactivated', function (): void {
    $workspace = User::factory()->create();
    $member = User::factory()->employee($workspace)->create();

    app(DeactivateAccountAction::class)->execute($workspace, 'temporary');
    app(ReactivateAccountAction::class)->execute($workspace);

    expect($workspace->fresh()->isDeactivated())->toBeFalse()
        ->and($member->fresh()->isDeactivated())->toBeFalse()
        ->and($member->fresh()->deactivation_reason)->toBeNull();
});

it('does not cascade when a member (non-workspace) is deactivated', function (): void {
    $workspace = User::factory()->create();
    $chatter = User::factory()->chatter($workspace)->create();

    app(DeactivateAccountAction::class)->execute($chatter, 'individual ban');

    expect($chatter->fresh()->isDeactivated())->toBeTrue()
        ->and($workspace->fresh()->isDeactivated())->toBeFalse();
});
