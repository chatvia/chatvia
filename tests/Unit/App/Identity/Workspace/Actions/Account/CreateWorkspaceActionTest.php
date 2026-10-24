<?php

use App\Identity\Workspace\Actions\Account\CreateWorkspaceAction;
use App\Identity\Workspace\Enums\WorkspaceRole;
use App\Identity\Workspace\Models\User;

/**
 * Creating the workspace of a new account: named after it, owned by it, with
 * a default team that becomes the account's current team.
 */

/** A saved account with no workspace yet (the factory gives every created user one). */
function cwaAccountWithoutWorkspace(): User
{
    $user = User::factory()->make(['name' => 'Dana']);
    $user->save();

    return $user;
}

it('creates a workspace named after the account, with the account as its owner', function (): void {
    $owner = cwaAccountWithoutWorkspace();

    $workspace = app(CreateWorkspaceAction::class)->execute($owner);

    expect($workspace->name)->toBe('Dana')
        ->and($workspace->external_id)->not->toBeEmpty()
        ->and($owner->workspace()->is($workspace))->toBeTrue()
        ->and($owner->workspaceRole())->toBe(WorkspaceRole::Owner)
        ->and($owner->isWorkspaceOwner())->toBeTrue()
        ->and($workspace->owner?->is($owner))->toBeTrue();
});

it('creates the default team and makes it the current team', function (): void {
    $owner = cwaAccountWithoutWorkspace();

    $workspace = app(CreateWorkspaceAction::class)->execute($owner);

    $team = $workspace->teams()->sole();

    expect($owner->fresh()?->current_team_id)->toBe($team->id)
        ->and($owner->ownsTeam($team))->toBeTrue();
});
