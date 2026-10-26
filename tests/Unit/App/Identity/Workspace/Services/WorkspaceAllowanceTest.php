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
    expect(app(WorkspaceAllowance::class)->canCreate())->toBeFalse()
        ->and(app(WorkspaceAllowance::class)->signUpOpen())->toBeFalse();

    $account = waAccountWithoutWorkspace();
    $count = Workspace::query()->count();

    expect(fn () => app(CreateWorkspaceAction::class)->execute($account))
        ->toThrow(WorkspaceLimitReachedException::class);

    expect(Workspace::query()->count())->toBe($count);
});

it('allows more workspaces with a license key', function (): void {
    withLicense();

    expect(app(WorkspaceAllowance::class)->canCreate())->toBeTrue()
        ->and(app(WorkspaceAllowance::class)->signUpOpen())->toBeTrue();

    $workspace = app(CreateWorkspaceAction::class)->execute(waAccountWithoutWorkspace());

    expect($workspace->exists)->toBeTrue();
});

it('keeps sign-up closed while the admin switch is off, even with a key', function (): void {
    withLicense();
    config(['chatvia.registration_enabled' => false]);

    expect(app(WorkspaceAllowance::class)->signUpOpen())->toBeFalse();
});

it('keeps workspaces made with a key after the key is removed, but refuses new ones', function (): void {
    withLicense();
    $workspace = app(CreateWorkspaceAction::class)->execute(waAccountWithoutWorkspace());

    withoutLicense();

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeTrue()
        ->and(app(WorkspaceAllowance::class)->canCreate())->toBeFalse();
});
