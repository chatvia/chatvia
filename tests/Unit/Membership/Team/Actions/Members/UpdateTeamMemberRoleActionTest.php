<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Members\UpdateTeamMemberRoleAction;
use Domain\Membership\Team\Exceptions\TeamOwnerImmutableException;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Services\TeamService;

/**
 * Moving a member between roles updates the pivot; the owner, who holds the
 * team without a role row, is never moved.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = app(TeamService::class)->createDefaultTeam($this->owner);
    $this->role = fn (string $name): Role => teamRole($this->team, $name, permissions: []);
});

it('moves a member to another of the team\'s roles', function (): void {
    [$from, $to] = [($this->role)('Support'), ($this->role)('Billing')];

    $member = User::factory()->employee($this->owner)->create();
    $this->team->members()->attach($member->id, ['role_id' => $from->id]);

    app(UpdateTeamMemberRoleAction::class)->execute($this->team, $member, $to->code);

    expect((int) $this->team->members()->whereKey($member->id)->first()->pivot->role_id)->toBe($to->id);
});

it('never changes the owner\'s role', function (): void {
    $role = ($this->role)('Support');

    expect(fn (): mixed => app(UpdateTeamMemberRoleAction::class)->execute($this->team, $this->owner, $role->code))
        ->toThrow(TeamOwnerImmutableException::class);
});
