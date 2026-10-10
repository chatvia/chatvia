<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserQuota;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    // Deleting a chatter wipes its avatar tree on the public bucket; fake the
    // disk so the delete tests stay hermetic and never reach real S3.
    Storage::fake('public');

    // Registration is trial-checkout-only now (the Stripe webhook provisions
    // billing/quota/default team), so provision explicitly. Bump the team
    // quota so tests that POST /api/v1/teams aren't blocked by the starter
    // ceiling of 1 (which the default team already fills).
    $this->user = User::factory()->create();
    $this->user->billing()->firstOrCreate([]);
    UserQuota::firstOrCreate(
        ['user_id' => $this->user->id],
        planFixedLimits(),
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
    it('answers 402 with the seat limit when every workspace seat is taken', function (): void {
        $this->user->quota()->update(['workspace_users_limit' => 0, 'extra_members_limit' => 0]);

        $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/chatters', [
                'name' => 'Overflow',
                'agent_ids' => [$this->agent->external_id],
                'email' => 'overflow@example.test',
                'password' => 'super-secret-pw',
            ])
            ->assertStatus(402)
            ->assertJsonPath('message', "You've reached your workspace user limit (0), shared between team members and chatters. Upgrade your plan to add more.");

        expect($this->user->chatters()->count())->toBe(0);
    });

    it('stops working once the account is deactivated', function (): void {
        $this->withHeaders(apiHeaders($this->token))
            ->getJson('/api/v1/teams')
            ->assertOk();

        $this->user->update(['deactivated_at' => now()]);
        app('auth')->forgetGuards();

        $this->withHeaders(apiHeaders($this->token))
            ->getJson('/api/v1/teams')
            ->assertUnauthorized();
    });

    it('creates a chatter granted to the given agent and returns its external id', function (): void {
        $body = $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/chatters', [
                'name' => 'Jane',
                'agent_ids' => [$this->agent->external_id],
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

    it('grants the chatter access to every agent supplied', function (): void {
        $second = createAgent($this->user);

        $body = $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/chatters', [
                'name' => 'Multi',
                'agent_ids' => [$this->agent->external_id, $second->external_id],
                'email' => 'multi@example.test',
                'password' => 'super-secret-pw',
            ])
            ->assertOk()
            ->json('data');

        $created = User::where('external_id', $body['id'])->firstOrFail();

        expect($created->accessibleAgents()->pluck('agents.id')->all())
            ->toHaveCount(2)
            ->toContain($this->agent->id)
            ->toContain($second->id);
    });

    it('requires a name', function (): void {
        $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/chatters', ['agent_ids' => [$this->agent->external_id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('requires at least one agent id', function (): void {
        $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/chatters', ['name' => 'Jane'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agent_ids']);
    });

    it('requires email and password (end-users are credentialed now)', function (): void {
        $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/chatters', [
                'name' => 'Jane',
                'agent_ids' => [$this->agent->external_id],
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
                'agent_ids' => [$otherAgent->external_id],
                'email' => 'jane@example.test',
                'password' => 'super-secret-pw',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agent_ids']);
    });

    it('rejects the whole request when any agent is foreign and creates no chatter', function (): void {
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $foreignAgent = createAgent($other);

        $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/chatters', [
                'name' => 'Jane',
                'agent_ids' => [$this->agent->external_id, $foreignAgent->external_id],
                'email' => 'jane@example.test',
                'password' => 'super-secret-pw',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agent_ids']);

        expect(User::where('email', 'jane@example.test')->exists())->toBeFalse();
    });
});

describe('DELETE /api/v1/chatters/{chatter}', function (): void {

    it('deletes a chatter in the workspace', function (): void {
        $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData($this->user, $this->agent, 'Jane'));

        $this->withHeaders(apiHeaders($this->token))
            ->deleteJson('/api/v1/chatters/'.$chatter->external_id)
            ->assertNoContent();

        expect(User::whereKey($chatter->id)->exists())->toBeFalse();
    });

    it('404s for a chatter owned by another account and leaves it intact', function (): void {
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $foreign = app(CreateChatterAction::class)->execute(new CreateChatterData($other, createAgent($other), 'Stranger'));

        $this->withHeaders(apiHeaders($this->token))
            ->deleteJson('/api/v1/chatters/'.$foreign->external_id)
            ->assertNotFound();

        expect(User::whereKey($foreign->id)->exists())->toBeTrue();
    });
});

describe('DELETE /api/v1/chatters/{chatter}/agents', function (): void {

    it('revokes access to the named agents and keeps the rest', function (): void {
        $second = createAgent($this->user);
        $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData(
            $this->user,
            new Collection([$this->agent, $second]),
            'Jane',
        ));

        $this->withHeaders(apiHeaders($this->token))
            ->deleteJson('/api/v1/chatters/'.$chatter->external_id.'/agents', [
                'agent_ids' => [$this->agent->external_id],
            ])
            ->assertNoContent();

        expect($chatter->accessibleAgents()->pluck('agents.id')->all())->toEqual([$second->id]);
    });

    it('requires at least one agent id', function (): void {
        $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData($this->user, $this->agent, 'Jane'));

        $this->withHeaders(apiHeaders($this->token))
            ->deleteJson('/api/v1/chatters/'.$chatter->external_id.'/agents', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agent_ids']);
    });

    it('rejects an agent owned by another account and keeps the grant', function (): void {
        $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData($this->user, $this->agent, 'Jane'));
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $foreignAgent = createAgent($other);

        $this->withHeaders(apiHeaders($this->token))
            ->deleteJson('/api/v1/chatters/'.$chatter->external_id.'/agents', [
                'agent_ids' => [$foreignAgent->external_id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agent_ids']);

        expect($chatter->accessibleAgents()->whereKey($this->agent->id)->exists())->toBeTrue();
    });

    it('404s for a chatter owned by another account', function (): void {
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $foreign = app(CreateChatterAction::class)->execute(new CreateChatterData($other, createAgent($other), 'Stranger'));

        $this->withHeaders(apiHeaders($this->token))
            ->deleteJson('/api/v1/chatters/'.$foreign->external_id.'/agents', [
                'agent_ids' => [$this->agent->external_id],
            ])
            ->assertNotFound();
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
