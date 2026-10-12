<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Actions\Account\ReactivateAccountAction;
use App\Identity\Workspace\Models\User;

/**
 * Deactivating a workspace is an account-wide off switch: every member it owns
 * (teammates in `users` + chatters in `chatters`) is switched off with the
 * owner, and reactivating the workspace restores them. Switching off a single
 * teammate or chatter does not cascade.
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

it('deactivates every chatter of the workspace', function (): void {
    $workspace = User::factory()->create();
    $chatters = Chatter::factory()->count(3)->ofWorkspace($workspace)->create();

    app(DeactivateAccountAction::class)->execute($workspace, 'abuse');

    expect($workspace->workspace()->chatters()->whereNull('deactivated_at')->exists())->toBeFalse()
        ->and($chatters->every(fn (Chatter $chatter): bool => $chatter->fresh()->isDeactivated()))->toBeTrue();
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

it('restores members when the workspace is reactivated', function (): void {
    $workspace = User::factory()->create();
    $member = User::factory()->employee($workspace)->create();
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create();

    app(DeactivateAccountAction::class)->execute($workspace, 'temporary');
    app(ReactivateAccountAction::class)->execute($workspace);

    expect($workspace->fresh()->isDeactivated())->toBeFalse()
        ->and($member->fresh()->isDeactivated())->toBeFalse()
        ->and($member->fresh()->deactivation_reason)->toBeNull()
        ->and($chatter->fresh()->isDeactivated())->toBeFalse()
        ->and($chatter->fresh()->deactivation_reason)->toBeNull();
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
