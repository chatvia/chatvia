<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Domain\Membership\Team\Actions\Team\UpdateTeamAction;
use Domain\Membership\Team\Data\UpdateTeamData;

/**
 * Renaming a team returns the saved team.
 */
it('renames the team and returns the fresh model', function (): void {
    $team = app(CreateDefaultTeamAction::class)->execute(User::factory()->create());

    $updated = app(UpdateTeamAction::class)->execute(new UpdateTeamData(team: $team, name: 'Support Squad'));

    expect($updated->name)->toBe('Support Squad')
        ->and($team->fresh()->name)->toBe('Support Squad');
});
