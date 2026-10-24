<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;

/**
 * Deleting a team (TeamDeleteController). The guards (primary team, agents,
 * members) are DeleteTeamAction's and answer as field errors through the
 * global mapping (DomainValidationResponseTest).
 */
it('lets the owner delete a team that is not the primary one and has no agents', function (): void {
    $owner = User::factory()->create();
    ownerTeam($owner);
    $team = app(CreateTeamAction::class)->execute(new CreateTeamData(workspace: $owner->workspace(), name: 'Deletable'));

    $this->actingAs($owner)
        ->from('/')
        ->delete(route('teams.destroy', $team))
        ->assertRedirect(route('teams.index'))
        ->assertSessionHasNoErrors();

    expect($team->fresh())->toBeNull();
});
