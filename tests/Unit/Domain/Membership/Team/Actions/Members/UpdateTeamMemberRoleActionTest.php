<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Members\UpdateTeamMemberRoleAction;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Domain\Membership\Team\Exceptions\TeamOwnerImmutableException;
use Domain\Membership\Team\Models\Role;

/**
 * The owner, who holds the team without a role row, is never moved to
 * another role.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = app(CreateDefaultTeamAction::class)->execute($this->owner);
    $this->role = fn (string $name): Role => teamRole($this->team, $name, permissions: []);
});

it('never changes the owner\'s role', function (): void {
    $role = ($this->role)('Support');

    expect(fn (): mixed => app(UpdateTeamMemberRoleAction::class)->execute($this->team, $this->owner, $role->code))
        ->toThrow(TeamOwnerImmutableException::class);
});
