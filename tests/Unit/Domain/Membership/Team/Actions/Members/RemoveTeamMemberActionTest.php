<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Members\RemoveTeamMemberAction;
use Domain\Membership\Team\Exceptions\TeamOwnerImmutableException;
use Domain\Membership\Team\Services\TeamService;

/**
 * One rule for the dashboard, "leave team" and the public API: a member's
 * pivot row goes, the owner never does.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = app(TeamService::class)->createDefaultTeam($this->owner);
});

it('detaches a member and keeps the account', function (): void {
    $member = User::factory()->employee($this->owner)->create();
    $role = teamRole($this->team, permissions: []);
    $this->team->members()->attach($member->id, ['role_id' => $role->id]);

    app(RemoveTeamMemberAction::class)->execute($this->team, $member);

    expect($this->team->members()->whereKey($member->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($member->id)->exists())->toBeTrue();
});

it('refuses to remove the team owner', function (): void {
    expect(fn (): mixed => app(RemoveTeamMemberAction::class)->execute($this->team, $this->owner))
        ->toThrow(TeamOwnerImmutableException::class);
});
