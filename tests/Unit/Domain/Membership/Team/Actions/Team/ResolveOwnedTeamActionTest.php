<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Team\ResolveOwnedTeamAction;
use Domain\Membership\Team\Services\TeamService;
use Support\Exceptions\DomainValidationException;

/**
 * Finding a team by its public id inside the caller's workspace; another
 * workspace's team is a `team_id` field error, as if it did not exist.
 */
it('resolves a team the owner owns', function (): void {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createDefaultTeam($owner);

    expect(app(ResolveOwnedTeamAction::class)->execute($owner->workspace(), $team->external_id)->is($team))->toBeTrue();
});

it('rejects another workspace team as a team_id field error', function (): void {
    $owner = User::factory()->create();
    $otherTeam = app(TeamService::class)->createDefaultTeam(User::factory()->create());

    try {
        app(ResolveOwnedTeamAction::class)->execute($owner->workspace(), $otherTeam->external_id);
        $this->fail('Expected the foreign team to be rejected.');
    } catch (DomainValidationException $e) {
        expect($e->field)->toBe('team_id')
            ->and($e->getMessage())->toBe('Team not found.');
    }
});
