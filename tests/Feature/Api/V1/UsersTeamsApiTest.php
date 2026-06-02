<?php

use App\Workspace\Models\User;
use App\Team\Models\Team;
use App\Team\Services\TeamService;
use Domain\Team\Actions\Team\CreateTeamAction;
use Domain\Team\Data\CreateTeamData;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->agent = createAgent($this->user);
    $this->token = $this->user->createToken('account')->plainTextToken;
});

function apiHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

describe('POST /api/v1/users', function (): void {

    it('creates a chatter granted to the given agent and returns its external id', function (): void {
        $body = $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/users', [
                'name' => 'Jane',
                'agent_id' => $this->agent->external_id,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Jane')
            ->assertJsonStructure(['data' => ['id', 'name', 'created_at']])
            ->json('data');

        // id is the opaque external id (uuid), not a guessable integer.
        expect($body['id'])->toBeString()->not->toBe('1');

        $created = User::where('external_id', $body['id'])->firstOrFail();

        expect($created->workspace_id)->toBe($this->user->id)
            ->and($created->isChatter())->toBeTrue()
            ->and($created->email)->toBeNull()
            ->and($created->accessibleAgents()->whereKey($this->agent->id)->exists())->toBeTrue();
    });

    it('requires a name', function (): void {
        $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/users', ['agent_id' => $this->agent->external_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('requires an agent_id', function (): void {
        $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/users', ['name' => 'Jane'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agent_id']);
    });

    it('rejects an agent owned by a different account', function (): void {
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $otherAgent = createAgent($other);

        $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/users', [
                'name' => 'Jane',
                'agent_id' => $otherAgent->external_id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agent_id']);
    });
});

describe('POST /api/v1/teams', function (): void {

    it('creates a team and returns its external id', function (): void {
        $body = $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/teams', ['name' => 'Acme Support'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Acme Support')
            ->assertJsonStructure(['data' => ['id', 'name', 'created_at']])
            ->json('data');

        expect($body['id'])->toBeString();

        $team = Team::where('external_id', $body['id'])->firstOrFail();
        expect($team->user_id)->toBe($this->user->id);
    });
});

describe('PATCH /api/v1/teams/{team}', function (): void {

    it('renames an owned team by external id', function (): void {
        $team = app(CreateTeamAction::class)->execute(
            new CreateTeamData(owner: $this->user, name: 'Old'),
        );

        $this->withHeaders(apiHeaders($this->token))
            ->patchJson("/api/v1/teams/{$team->external_id}", ['name' => 'New'])
            ->assertOk()
            ->assertJsonPath('data.id', $team->external_id)
            ->assertJsonPath('data.name', 'New');
    });

    it('forbids renaming another account’s team', function (): void {
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));

        $this->withHeaders(apiHeaders($this->token))
            ->patchJson("/api/v1/teams/{$other->primaryTeam()->external_id}", ['name' => 'evil'])
            ->assertForbidden();
    });
});
