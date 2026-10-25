<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Models\User;

/**
 * Deactivating a workspace is an account-wide off switch: every member it owns
 * (teammates in `users` + chatters in `chatters`) is switched off with the
 * owner, with the owner's reason. Switching off a single teammate or chatter
 * does not cascade, and other workspaces are never touched.
 */
it('deactivates every member when the workspace is deactivated', function (): void {
    $workspace = User::factory()->create();
    $employee = User::factory()->employee($workspace)->create();
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create();

    app(DeactivateAccountAction::class)->execute($workspace, 'Terms of service violation');

    expect($workspace->fresh()->isDeactivated())->toBeTrue()
        ->and($employee->fresh()->isDeactivated())->toBeTrue()
        ->and($chatter->fresh()->isDeactivated())->toBeTrue()
        ->and($employee->fresh()->deactivation_reason)->toBe('Terms of service violation')
        ->and($chatter->fresh()->deactivation_reason)->toBe('Terms of service violation');
});

it('keeps the original timestamp and reason of a chatter that was already off', function (): void {
    $workspace = User::factory()->create();
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create([
        'deactivated_at' => now()->subWeek(),
        'deactivation_reason' => 'individual ban',
    ]);
    $offSince = $chatter->fresh()->deactivated_at;

    app(DeactivateAccountAction::class)->execute($workspace, 'workspace closed');

    $chatter->refresh();
    expect($chatter->deactivated_at->equalTo($offSince))->toBeTrue()
        ->and($chatter->deactivation_reason)->toBe('individual ban');
});

it('does not touch members of other workspaces', function (): void {
    $workspace = User::factory()->create();
    $member = Chatter::factory()->ofWorkspace($workspace)->create();

    $otherWorkspace = User::factory()->create();
    $otherMember = Chatter::factory()->ofWorkspace($otherWorkspace)->create();
    $otherEmployee = User::factory()->employee($otherWorkspace)->create();

    app(DeactivateAccountAction::class)->execute($workspace, 'abuse');

    expect($member->fresh()->isDeactivated())->toBeTrue()
        ->and($otherWorkspace->fresh()->isDeactivated())->toBeFalse()
        ->and($otherMember->fresh()->isDeactivated())->toBeFalse()
        ->and($otherEmployee->fresh()->isDeactivated())->toBeFalse();
});

it('does not touch a staff account whose id equals a chatter id of the workspace', function (): void {
    $workspace = User::factory()->create();
    $unrelated = User::factory()->employee(User::factory()->create())->create();
    // Chatter and staff ids come from different tables and can be equal.
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create(['id' => $unrelated->id]);

    app(DeactivateAccountAction::class)->execute($workspace, 'abuse');

    expect($chatter->fresh()->isDeactivated())->toBeTrue()
        ->and($unrelated->fresh()->isDeactivated())->toBeFalse();
});

it('does not cascade when a chatter is deactivated', function (): void {
    $workspace = User::factory()->create();
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create();
    $sibling = Chatter::factory()->ofWorkspace($workspace)->create();

    app(DeactivateAccountAction::class)->execute($chatter, 'individual ban');

    expect($chatter->fresh()->isDeactivated())->toBeTrue()
        ->and($sibling->fresh()->isDeactivated())->toBeFalse()
        ->and($workspace->fresh()->isDeactivated())->toBeFalse();
});

it('does not cascade when a teammate is deactivated', function (): void {
    $workspace = User::factory()->create();
    $employee = User::factory()->employee($workspace)->create();
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create();

    app(DeactivateAccountAction::class)->execute($employee, 'left the company');

    expect($employee->fresh()->isDeactivated())->toBeTrue()
        ->and($chatter->fresh()->isDeactivated())->toBeFalse()
        ->and($workspace->fresh()->isDeactivated())->toBeFalse();
});

it('gives every account it switches off a new remember-me token', function (): void {
    // A "remember me" cookie from before must not sign anyone back in, not
    // even once the workspace is reactivated.
    $workspace = User::factory()->create(['remember_token' => 'owner-token']);
    $employee = User::factory()->employee($workspace)->create(['remember_token' => 'employee-token']);
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create(['remember_token' => 'chatter-token']);
    $other = Chatter::factory()->ofWorkspace($workspace)->create(['remember_token' => 'other-token']);

    app(DeactivateAccountAction::class)->execute($workspace, 'abuse');

    $tokens = [$workspace->fresh()->remember_token, $employee->fresh()->remember_token, $chatter->fresh()->remember_token, $other->fresh()->remember_token];

    expect($tokens)->each->not->toBeIn(['owner-token', 'employee-token', 'chatter-token', 'other-token'])
        ->and(array_unique($tokens))->toHaveCount(4);
});
