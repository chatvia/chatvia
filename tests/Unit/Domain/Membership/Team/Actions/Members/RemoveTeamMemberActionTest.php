<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Members\RemoveTeamMemberAction;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Domain\Membership\Team\Exceptions\TeamOwnerImmutableException;

/**
 * One rule for the dashboard, "leave team" and the public API: the team
 * owner is never removed.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = app(CreateDefaultTeamAction::class)->execute($this->owner);
});

it('refuses to remove the team owner', function (): void {
    expect(fn (): mixed => app(RemoveTeamMemberAction::class)->execute($this->team, $this->owner))
        ->toThrow(TeamOwnerImmutableException::class);
});
