<?php

/**
 * The account API for chatters and teams: creating chatters (unique email per
 * workspace, the same email allowed in another workspace or on a staff
 * account), deleting them, revoking their agents, and managing teams.
 */

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    // Deleting a chatter wipes its avatar tree on the assets disk and its
    // attachment files on the public bucket; fake both so the delete tests
    // stay hermetic and never reach real S3.
    Storage::fake('public');
    Storage::fake('assets');

    $this->user = User::factory()->create();
    $this->agent = createAgent($this->user);
    $this->token = $this->user->createToken('account')->plainTextToken;
});

function apiHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

describe('POST /api/v1/chatters', function (): void {
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
            ->assertCreated()
            ->assertJsonPath('data.name', 'Jane')
            ->assertJsonStructure(['data' => ['id', 'name', 'created_at']])
            ->json('data');

        // id is the opaque external id (uuid), not a guessable integer.
        expect($body['id'])->toBeString()->not->toBe('1');

        $created = Chatter::where('external_id', $body['id'])->firstOrFail();

        expect($created->workspace_id)->toBe($this->user->workspace()->id)
            ->and($created->email)->toBe('jane@example.test')
            ->and($created->canAccessAgent($this->agent))->toBeTrue()
            ->and(User::where('external_id', $body['id'])->exists())->toBeFalse();
    });

    it('rejects an email the workspace already uses for a chatter, without case', function (): void {
        Chatter::factory()->ofWorkspace($this->user)->create(['email' => 'jane@example.test']);

        $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/chatters', [
                'name' => 'Jane again',
                'agent_ids' => [$this->agent->external_id],
                'email' => 'JANE@example.test',
                'password' => 'super-secret-pw',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        expect($this->user->workspace()->chatters()->whereRaw('lower(email) = ?', ['jane@example.test'])->count())->toBe(1);
    });

    it('creates a chatter whose email is a chatter of another workspace, as a separate row', function (): void {
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $foreign = Chatter::factory()->grantedTo(createAgent($other))->create(['email' => 'jane@example.test']);

        $body = $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/chatters', [
                'name' => 'Jane',
                'agent_ids' => [$this->agent->external_id],
                'email' => 'jane@example.test',
                'password' => 'super-secret-pw',
            ])
            ->assertCreated()
            ->json('data');

        $created = Chatter::where('external_id', $body['id'])->firstOrFail();

        expect($created->id)->not->toBe($foreign->id)
            ->and($created->workspace_id)->toBe($this->user->workspace()->id)
            ->and($foreign->fresh()->workspace_id)->toBe($other->workspace()->id);
    });

    it('creates a chatter with the email of a staff account', function (): void {
        $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/chatters', [
                'name' => 'Me as a chatter',
                'agent_ids' => [$this->agent->external_id],
                'email' => $this->user->email,
                'password' => 'super-secret-pw',
            ])
            ->assertCreated();

        expect($this->user->workspace()->chatters()->where('email', $this->user->email)->exists())->toBeTrue();
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
            ->assertCreated()
            ->json('data');

        $created = Chatter::where('external_id', $body['id'])->firstOrFail();

        expect($created->agents()->pluck('agents.id')->all())
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

        expect(Chatter::where('email', 'jane@example.test')->where('workspace_id', $this->user->workspace()->id)->exists())->toBeFalse();
    });
});

describe('DELETE /api/v1/chatters/{chatter}', function (): void {

    it('deletes a chatter in the workspace', function (): void {
        $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData($this->user->workspace(), $this->agent, 'Jane'));

        $this->withHeaders(apiHeaders($this->token))
            ->deleteJson('/api/v1/chatters/'.$chatter->external_id)
            ->assertNoContent();

        expect(Chatter::whereKey($chatter->id)->exists())->toBeFalse();
    });

    it('404s for a chatter owned by another account and leaves it intact', function (): void {
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $foreign = app(CreateChatterAction::class)->execute(new CreateChatterData($other->workspace(), createAgent($other), 'Stranger'));

        $this->withHeaders(apiHeaders($this->token))
            ->deleteJson('/api/v1/chatters/'.$foreign->external_id)
            ->assertNotFound();

        expect(Chatter::whereKey($foreign->id)->exists())->toBeTrue();
    });
});

describe('DELETE /api/v1/chatters/{chatter}/agents', function (): void {

    it('revokes access to the named agents and keeps the rest', function (): void {
        $second = createAgent($this->user);
        $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData(
            $this->user->workspace(),
            new Collection([$this->agent, $second]),
            'Jane',
        ));

        $this->withHeaders(apiHeaders($this->token))
            ->deleteJson('/api/v1/chatters/'.$chatter->external_id.'/agents', [
                'agent_ids' => [$this->agent->external_id],
            ])
            ->assertNoContent();

        expect($chatter->agents()->pluck('agents.id')->all())->toEqual([$second->id]);
    });

    it('requires at least one agent id', function (): void {
        $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData($this->user->workspace(), $this->agent, 'Jane'));

        $this->withHeaders(apiHeaders($this->token))
            ->deleteJson('/api/v1/chatters/'.$chatter->external_id.'/agents', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agent_ids']);
    });

    it('rejects an agent owned by another account and keeps the grant', function (): void {
        $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData($this->user->workspace(), $this->agent, 'Jane'));
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $foreignAgent = createAgent($other);

        $this->withHeaders(apiHeaders($this->token))
            ->deleteJson('/api/v1/chatters/'.$chatter->external_id.'/agents', [
                'agent_ids' => [$foreignAgent->external_id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agent_ids']);

        expect($chatter->agents()->whereKey($this->agent->id)->exists())->toBeTrue();
    });

    it('404s for a chatter owned by another account', function (): void {
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $foreign = app(CreateChatterAction::class)->execute(new CreateChatterData($other->workspace(), createAgent($other), 'Stranger'));

        $this->withHeaders(apiHeaders($this->token))
            ->deleteJson('/api/v1/chatters/'.$foreign->external_id.'/agents', [
                'agent_ids' => [$this->agent->external_id],
            ])
            ->assertNotFound();

        expect($foreign->agents()->count())->toBe(1);
    });
});

describe('POST /api/v1/teams', function (): void {

    it('creates a team and returns its external id', function (): void {
        $body = $this->withHeaders(apiHeaders($this->token))
            ->postJson('/api/v1/teams', ['name' => 'Acme Support'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Acme Support')
            ->assertJsonStructure(['data' => ['id', 'name', 'created_at']])
            ->json('data');

        expect($body['id'])->toBeString();

        $team = Team::where('external_id', $body['id'])->firstOrFail();
        expect($team->workspace_id)->toBe($this->user->workspace()->id);
    });
});

describe('PATCH /api/v1/teams/{team}', function (): void {

    it('renames an owned team by external id', function (): void {
        $team = app(CreateTeamAction::class)->execute(
            new CreateTeamData(workspace: $this->user->workspace(), name: 'Old'),
        );

        $this->withHeaders(apiHeaders($this->token))
            ->patchJson("/api/v1/teams/{$team->external_id}", ['name' => 'New'])
            ->assertOk()
            ->assertJsonPath('data.id', $team->external_id)
            ->assertJsonPath('data.name', 'New');
    });

    it('hides another account’s team from a rename', function (): void {
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));

        $this->withHeaders(apiHeaders($this->token))
            ->patchJson("/api/v1/teams/{$other->primaryTeam()->external_id}", ['name' => 'evil'])
            ->assertNotFound();
    });
});
