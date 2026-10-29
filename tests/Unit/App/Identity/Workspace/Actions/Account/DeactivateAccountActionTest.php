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
it('switches every member off with the owner\'s reason and a new remember-me token, keeping an earlier ban as it was', function (): void {
    $workspace = User::factory()->create(['remember_token' => 'owner-token']);
    $employee = User::factory()->employee($workspace)->create(['remember_token' => 'employee-token']);
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create(['remember_token' => 'chatter-token']);
    $banned = Chatter::factory()->ofWorkspace($workspace)->create([
        'deactivated_at' => now()->subWeek(),
        'deactivation_reason' => 'individual ban',
    ]);
    $bannedSince = $banned->fresh()->deactivated_at;

    app(DeactivateAccountAction::class)->execute($workspace, 'Terms of service violation');

    $tokens = [$workspace->fresh()->remember_token, $employee->fresh()->remember_token, $chatter->fresh()->remember_token];
    expect($workspace->fresh()->isDeactivated())->toBeTrue()
        ->and($employee->fresh())->isDeactivated()->toBeTrue()->deactivation_reason->toBe('Terms of service violation')
        ->and($chatter->fresh())->isDeactivated()->toBeTrue()->deactivation_reason->toBe('Terms of service violation')
        ->and($banned->fresh()->deactivated_at->equalTo($bannedSince))->toBeTrue()
        ->and($banned->fresh()->deactivation_reason)->toBe('individual ban')
        ->and($tokens)->each->not->toBeIn(['owner-token', 'employee-token', 'chatter-token'])
        ->and(array_unique($tokens))->toHaveCount(3);
});

it('leaves other workspaces alone, even a staff account whose id equals a chatter id of the workspace', function (): void {
    $workspace = User::factory()->create();
    $otherWorkspace = User::factory()->create();
    $otherMember = Chatter::factory()->ofWorkspace($otherWorkspace)->create();
    $otherEmployee = User::factory()->employee($otherWorkspace)->create(['id' => 900002]);
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create(['id' => $otherEmployee->id]);

    app(DeactivateAccountAction::class)->execute($workspace, 'abuse');

    expect($chatter->fresh()->isDeactivated())->toBeTrue()
        ->and($otherWorkspace->fresh()->isDeactivated())->toBeFalse()
        ->and($otherMember->fresh()->isDeactivated())->toBeFalse()
        ->and($otherEmployee->fresh()->isDeactivated())->toBeFalse();
});

it('does not cascade when a single chatter or teammate is switched off', function (): void {
    $workspace = User::factory()->create();
    $employee = User::factory()->employee($workspace)->create();
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create();
    $sibling = Chatter::factory()->ofWorkspace($workspace)->create();

    app(DeactivateAccountAction::class)->execute($chatter, 'individual ban');
    app(DeactivateAccountAction::class)->execute($employee, 'left the company');

    expect($chatter->fresh()->isDeactivated())->toBeTrue()
        ->and($employee->fresh()->isDeactivated())->toBeTrue()
        ->and($sibling->fresh()->isDeactivated())->toBeFalse()
        ->and($workspace->fresh()->isDeactivated())->toBeFalse();
});
