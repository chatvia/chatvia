<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Actions\Team\DeleteTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Services\TeamService;
use Support\Exceptions\DomainValidationException;

/*
|--------------------------------------------------------------------------
| DeleteTeamAction
|--------------------------------------------------------------------------
|
| The guards a workspace cannot lose, shared by the dashboard and the API:
| the owner's primary team, their only team, and a team with agents stay.
*/

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
});

it('deletes a secondary, empty team', function (): void {
    $team = app(CreateTeamAction::class)->execute(new CreateTeamData(workspace: $this->owner->workspace(), name: 'Spare'));

    app(DeleteTeamAction::class)->execute($this->owner, $team);

    expect($team->fresh())->toBeNull();
});

it('refuses the primary team', function (): void {
    app(CreateTeamAction::class)->execute(new CreateTeamData(workspace: $this->owner->workspace(), name: 'Spare'));
    $primary = ownerTeam($this->owner);

    app(DeleteTeamAction::class)->execute($this->owner, $primary);
})->throws(DomainValidationException::class, 'primary team');

it('refuses the only team (as the primary)', function (): void {
    // The sole team is always the primary one, so that guard answers first.
    app(DeleteTeamAction::class)->execute($this->owner, ownerTeam($this->owner));
})->throws(DomainValidationException::class, 'primary team');

it('refuses a team that still has agents', function (): void {
    $team = app(CreateTeamAction::class)->execute(new CreateTeamData(workspace: $this->owner->workspace(), name: 'Spare'));
    createAgent($this->owner, ['team_id' => $team->id]);

    app(DeleteTeamAction::class)->execute($this->owner, $team);
})->throws(DomainValidationException::class, 'has agents');
