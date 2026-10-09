<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Actions\Roles\DeleteTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;
use Support\Exceptions\DomainValidationException;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = app(TeamService::class)->createDefaultTeam($this->owner);
    $this->role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $this->team,
        name: 'Support',
        permissions: [],
        allAgents: true,
        agentExternalIds: [],
    ));
});

it('deletes a role nobody holds', function (): void {
    app(DeleteTeamRoleAction::class)->execute($this->team, $this->role);

    expect($this->team->roles()->whereKey($this->role->id)->exists())->toBeFalse();
});

it('refuses to delete a role a member still holds', function (): void {
    $member = User::factory()->create();
    $this->team->members()->attach($member->id, ['role_id' => $this->role->id]);

    expect(fn () => app(DeleteTeamRoleAction::class)->execute($this->team, $this->role))
        ->toThrow(DomainValidationException::class, 'Cannot delete this role. 1 member(s) are assigned to it.');

    expect($this->team->roles()->whereKey($this->role->id)->exists())->toBeTrue();
});
