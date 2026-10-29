<?php

use Domain\Membership\Team\Models\Team;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * The public API's teams: creating one in the workspace, and refusing to
 * delete a team it may not delete (a field error). Which teams can be
 * deleted is DeleteTeamAction's rule.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
});

it('creates a team in the workspace and answers with its public id', function (): void {
    $id = $this->withToken($this->token)
        ->postJson('/api/v1/teams', ['name' => 'Acme Support'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Acme Support')
        ->json('data.id');

    expect(Team::where('external_id', $id)->firstOrFail()->workspace_id)->toBe($this->owner->workspace()->id);
});

it('answers a team it may not delete with a field error and keeps it', function (): void {
    $team = ownerTeam($this->owner);

    $this->withToken($this->token)
        ->deleteJson("/api/v1/teams/{$team->external_id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['team']);

    expect($team->fresh())->not->toBeNull();
});
