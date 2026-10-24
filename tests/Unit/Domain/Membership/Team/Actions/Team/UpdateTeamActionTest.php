<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Team\UpdateTeamAction;
use Domain\Membership\Team\Data\UpdateTeamData;
use Domain\Membership\Team\Services\TeamService;

/**
 * Renaming a team returns the saved team.
 */
it('renames the team and returns the fresh model', function (): void {
    $team = app(TeamService::class)->createDefaultTeam(User::factory()->create());

    $updated = app(UpdateTeamAction::class)->execute(new UpdateTeamData(team: $team, name: 'Support Squad'));

    expect($updated->name)->toBe('Support Squad')
        ->and($team->fresh()->name)->toBe('Support Squad');
});
