<?php

use App\Auth\Models\User;
use App\Teams\Models\Team;
use App\Teams\Services\TeamService;
use Domain\Teams\Actions\CreateTeamAction;
use Domain\Teams\Data\CreateTeamData;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->token = $this->user->createToken('account')->plainTextToken;
});

function apiHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

describe('POST /api/v1/users', function (): void {

    it('creates an external end-user and returns its external id', function (): void {
        $body = $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/users', ['name' => 'Jane', 'email' => 'jane@acme.test'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Jane')
            ->assertJsonPath('data.contact_email', 'jane@acme.test')
            ->assertJsonPath('data.created_via', 'external')
            ->assertJsonStructure(['data' => ['id', 'name', 'contact_email', 'created_via', 'created_at']])
            ->json('data');

        // id is the opaque external id (uuid), not a guessable integer.
        expect($body['id'])->toBeString()->not->toBe('1');

        $created = User::where('external_id', $body['id'])->firstOrFail();
        expect($created->managed_by_user_id)->toBe($this->user->id)
            ->and($created->email)->toBeNull();
    });

    it('requires a name', function (): void {
        $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
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

    it('creates a chat-only team', function (): void {
        $body = $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/teams', ['name' => 'Customers', 'can_access_dashboard' => false])
            ->assertOk()
            ->assertJsonPath('data.can_access_dashboard', false)
            ->json('data');

        expect(Team::where('external_id', $body['id'])->firstOrFail()->can_access_dashboard)->toBeFalse();
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
