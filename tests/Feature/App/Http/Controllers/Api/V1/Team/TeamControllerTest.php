<?php

use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Models\Team;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * The public API's teams: listing them with their agents and roles nested,
 * creating, showing, renaming and deleting one. Which teams can be deleted
 * is DeleteTeamAction's rule and is tested there; this file checks that a
 * refusal answers as a field error.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->team = ownerTeam($this->owner);
});

it('lists each team once with its agents and roles nested', function (): void {
    teamRole($this->team, 'Support', permissions: []);

    $this->withToken($this->token)
        ->getJson('/api/v1/teams')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->team->external_id)
        ->assertJsonStructure(['data' => [[
            'id', 'name', 'created_at',
            'agents' => [['id', 'name']],
            'roles' => [['id', 'name', 'all_agents', 'agent_ids', 'permissions']],
        ]]]);
});

it('creates a team in the workspace and answers with its public id', function (): void {
    $id = $this->withToken($this->token)
        ->postJson('/api/v1/teams', ['name' => 'Acme Support'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Acme Support')
        ->assertJsonStructure(['data' => ['id', 'name', 'created_at']])
        ->json('data.id');

    expect(Team::where('external_id', $id)->firstOrFail()->workspace_id)->toBe($this->owner->workspace()->id);
});

it('refuses to create a team without a license key', function (): void {
    saveLicenseKey('');

    $this->withToken($this->token)
        ->postJson('/api/v1/teams', ['name' => 'Acme Support'])
        ->assertForbidden();

    expect($this->owner->workspace()->teams()->count())->toBe(1);
});

it('shows a team with its roles and agents nested', function (): void {
    $this->withToken($this->token)
        ->getJson("/api/v1/teams/{$this->team->external_id}")
        ->assertOk()
        ->assertJsonPath('data.id', $this->team->external_id)
        ->assertJsonStructure(['data' => ['roles', 'agents']]);
});

it('renames a team', function (): void {
    $this->withToken($this->token)
        ->patchJson("/api/v1/teams/{$this->team->external_id}", ['name' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.id', $this->team->external_id)
        ->assertJsonPath('data.name', 'New');
});

it('deletes a spare empty team', function (): void {
    $spare = app(CreateTeamAction::class)->execute(new CreateTeamData($this->owner->workspace(), 'Spare'));

    $this->withToken($this->token)->deleteJson("/api/v1/teams/{$spare->external_id}")->assertNoContent();

    expect($spare->fresh())->toBeNull();
});

it('answers a team it may not delete with a field error and keeps it', function (): void {
    $this->withToken($this->token)
        ->deleteJson("/api/v1/teams/{$this->team->external_id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['team']);

    expect($this->team->fresh())->not->toBeNull();
});
