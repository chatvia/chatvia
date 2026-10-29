<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Actions\Team\DeleteTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Support\Exceptions\DomainValidationException;

/**
 * DeleteTeamAction keeps the guards a workspace cannot lose, shared by the
 * dashboard and the API: the owner's primary team, their only team, a team
 * with agents (trashed ones too) and a team with members stay.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(CreateDefaultTeamAction::class)->execute($this->owner);
});

it('refuses the primary team, also when it is the only one', function (bool $withSpare): void {
    if ($withSpare) {
        app(CreateTeamAction::class)->execute(new CreateTeamData(workspace: $this->owner->workspace(), name: 'Spare'));
    }
    $primary = ownerTeam($this->owner);

    expect(fn () => app(DeleteTeamAction::class)->execute($this->owner, $primary))
        ->toThrow(DomainValidationException::class, 'primary team')
        ->and($primary->fresh())->not->toBeNull();
})->with([
    'with a spare team' => true,
    'as the only team' => false,
]);

it('refuses a team that still has agents, trashed ones waiting for their purge too', function (bool $trashed): void {
    $team = app(CreateTeamAction::class)->execute(new CreateTeamData(workspace: $this->owner->workspace(), name: 'Spare'));
    $agent = createAgent($this->owner, ['team_id' => $team->id]);
    if ($trashed) {
        $agent->delete();
    }

    expect(fn () => app(DeleteTeamAction::class)->execute($this->owner, $team))
        ->toThrow(DomainValidationException::class, 'has agents')
        ->and($team->fresh())->not->toBeNull();
})->with([
    'a live agent' => false,
    'a trashed agent' => true,
]);

it('refuses a team that still has members', function (): void {
    $team = app(CreateTeamAction::class)->execute(new CreateTeamData(workspace: $this->owner->workspace(), name: 'Spare'));
    $member = User::factory()->employee($this->owner)->create();
    $team->members()->attach($member->id, ['role_id' => $team->roles()->value('id')]);

    expect(fn () => app(DeleteTeamAction::class)->execute($this->owner, $team))
        ->toThrow(DomainValidationException::class, 'has members')
        ->and($team->fresh())->not->toBeNull();
});
