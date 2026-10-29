<?php

use App\Identity\Workspace\Actions\Account\CreateWorkspaceAction;
use App\Identity\Workspace\Exceptions\WorkspaceLimitReachedException;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use App\Identity\Workspace\Services\WorkspaceAllowance;

/**
 * Without a license key an install holds one workspace (a free-use limit):
 * once it exists, no other is made and sign-up closes. Any valid key lifts
 * the limit, and workspaces made with a key stay after it is removed.
 */
beforeEach(function (): void {
    Workspace::factory()->create();
});

/** A saved account with no workspace yet (the factory gives every created user one). */
function waAccountWithoutWorkspace(): User
{
    $user = User::factory()->make();
    $user->save();

    return $user;
}

it('refuses a second workspace without a license key', function (): void {
    $account = waAccountWithoutWorkspace();
    $count = Workspace::query()->count();

    expect(app(WorkspaceAllowance::class)->canCreate())->toBeFalse()
        ->and(app(WorkspaceAllowance::class)->signUpOpen())->toBeFalse()
        ->and(fn () => app(CreateWorkspaceAction::class)->execute($account))->toThrow(WorkspaceLimitReachedException::class)
        ->and(Workspace::query()->count())->toBe($count);
});

it('allows more workspaces with a license key, with sign-up still closed while the admin switch is off', function (): void {
    withLicense();
    $openBefore = app(WorkspaceAllowance::class)->signUpOpen();

    $workspace = app(CreateWorkspaceAction::class)->execute(waAccountWithoutWorkspace());
    config(['chatvia.registration_enabled' => false]);

    expect(app(WorkspaceAllowance::class)->canCreate())->toBeTrue()
        ->and($openBefore)->toBeTrue()
        ->and($workspace->exists)->toBeTrue()
        ->and(app(WorkspaceAllowance::class)->signUpOpen())->toBeFalse();
});

it('keeps workspaces made with a key after the key is removed, but refuses new ones', function (): void {
    withLicense();
    $workspace = app(CreateWorkspaceAction::class)->execute(waAccountWithoutWorkspace());

    withoutLicense();

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeTrue()
        ->and(app(WorkspaceAllowance::class)->canCreate())->toBeFalse();
});
