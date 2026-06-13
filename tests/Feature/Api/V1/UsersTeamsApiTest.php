<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserQuota;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;

beforeEach(function (): void {
    // Registration is trial-checkout-only now (the Stripe webhook provisions
    // billing/quota/default team), so provision explicitly. Bump the team
    // quota so tests that POST /api/v1/teams aren't blocked by the starter
    // ceiling of 1 (which the default team already fills).
    $this->user = User::factory()->create();
    $this->user->billing()->firstOrCreate([]);
    UserQuota::firstOrCreate(
        ['user_id' => $this->user->id],
        config('billing.packages.starter.quota_limits.fixed', []),
    );
    $this->user->quota()->update(['teams_limit' => 10]);
    $this->agent = createAgent($this->user);
    $this->token = $this->user->createToken('account')->plainTextToken;
});

function apiHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

describe('POST /api/v1/chatters', function (): void {

    it('creates a chatter granted to the given agent and returns its external id', function (): void {
        $body = $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/chatters', [
                'name' => 'Jane',
                'agent_id' => $this->agent->external_id,
                'email' => 'jane@example.test',
                'password' => 'super-secret-pw',
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
            ->and($created->email)->toBe('jane@example.test')
            ->and($created->accessibleAgents()->whereKey($this->agent->id)->exists())->toBeTrue();
    });

    it('requires a name', function (): void {
        $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/chatters', ['agent_id' => $this->agent->external_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('requires an agent_id', function (): void {
        $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/chatters', ['name' => 'Jane'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agent_id']);
    });

    it('requires email and password (end-users are credentialed now)', function (): void {
        $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/chatters', [
                'name' => 'Jane',
                'agent_id' => $this->agent->external_id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    });

    it('rejects an agent owned by a different account', function (): void {
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $otherAgent = createAgent($other);

        $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/chatters', [
                'name' => 'Jane',
                'agent_id' => $otherAgent->external_id,
                'email' => 'jane@example.test',
                'password' => 'super-secret-pw',
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
