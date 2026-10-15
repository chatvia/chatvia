<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentSignInTokenService;
use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);

    // One account-level key authenticates everything — no per-agent tokens,
    // no abilities.
    $this->token = $this->user->createToken('account')->plainTextToken;
});

function authHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

describe('POST /api/v1/agents', function (): void {

    it('creates a knowledge-base agent and returns just the agent', function (): void {
        $response = $this->withHeaders(authHeaders($this->token))
            ->postJson('/api/v1/agents', [
                'name' => 'Support Bot',
                'team_id' => $this->user->primaryTeam()->external_id,
                'is_public' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Support Bot')
            ->assertJsonPath('data.source', 'api')
            ->assertJsonStructure(['data' => ['id', 'url', 'permanent_url']]);

        // No per-agent token is minted anymore — the account key is the only key.
        expect($response->json())->not->toHaveKey('token');
    });

    it('creates an agent in a specific team via team_id', function (): void {
        $team = app(CreateTeamAction::class)->execute(
            new CreateTeamData(workspace: $this->user->workspace(), name: 'Side Team'),
        );

        $this->withHeaders(authHeaders($this->token))
            ->postJson('/api/v1/agents', ['name' => 'Team Bot', 'team_id' => $team->external_id, 'is_public' => false])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Team Bot');

        expect($team->agents()->where('name', 'Team Bot')->exists())->toBeTrue();
    });

    it('requires team_id', function (): void {
        $this->withHeaders(authHeaders($this->token))
            ->postJson('/api/v1/agents', ['name' => 'Bot'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['team_id']);
    });

    it('requires an explicit is_public choice (agents default to protected)', function (): void {
        $this->withHeaders(authHeaders($this->token))
            ->postJson('/api/v1/agents', [
                'name' => 'Bot',
                'team_id' => $this->user->primaryTeam()->external_id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_public']);
    });

    it('rejects a team_id the caller does not own', function (): void {
        // Factory users get no default team anymore — provision one explicitly.
        $otherUser = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));

        $this->withHeaders(authHeaders($this->token))
            ->postJson('/api/v1/agents', ['name' => 'Bot', 'team_id' => $otherUser->primaryTeam()->external_id, 'is_public' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['team_id']);
    });

    it('rejects unauthenticated requests', function (): void {
        $this->postJson('/api/v1/agents', ['name' => 'x'])->assertUnauthorized();
    });

    it('validates name', function (): void {
        $this->withHeaders(authHeaders($this->token))
            ->postJson('/api/v1/agents', [
                'name' => 'x',
                'team_id' => $this->user->primaryTeam()->external_id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });
});

describe('GET /api/v1/agents', function (): void {

    it('lists agents across all teams and nests the team object', function (): void {
        $teamB = app(CreateTeamAction::class)->execute(new CreateTeamData(workspace: $this->user->workspace(), name: 'Team B'));
        createAgentForUser($this->user, 'In Primary');
        createAgentInTeam($this->user, $teamB->id, 'In B');

        $body = $this->withHeaders(authHeaders($this->token))
            ->getJson('/api/v1/agents')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json('data');

        expect($body[0]['team']['id'])->toBeString()
            ->and($body[0]['team']['name'])->toBeString();
    });

    it('filters agents by team_id', function (): void {
        $teamB = app(CreateTeamAction::class)->execute(new CreateTeamData(workspace: $this->user->workspace(), name: 'Team B'));
        createAgentForUser($this->user, 'In Primary');
        createAgentInTeam($this->user, $teamB->id, 'In B');

        $this->withHeaders(authHeaders($this->token))
            ->getJson('/api/v1/agents?team_id='.$teamB->external_id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'In B')
            ->assertJsonPath('data.0.team.id', $teamB->external_id)
            ->assertJsonPath('data.0.team.name', 'Team B');
    });
});

describe('PATCH /api/v1/agents/{agent}', function (): void {

    it('patches metadata + flags', function (): void {
        $agent = createAgentForUser($this->user);

        $this->withHeaders(authHeaders($this->token))
            ->patchJson("/api/v1/agents/{$agent->external_id}", [
                'metadata' => ['external_user_id' => 'cus_42'],
                'is_public' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.metadata.external_user_id', 'cus_42')
            ->assertJsonPath('data.is_public', true);
    });

    it('hides an agent owned by another account', function (): void {
        $otherUser = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $otherAgent = createAgentForUser($otherUser);

        $this->withHeaders(authHeaders($this->token))
            ->patchJson("/api/v1/agents/{$otherAgent->external_id}", ['name' => 'evil'])
            ->assertNotFound();
    });
});

describe('DELETE /api/v1/agents/{agent}', function (): void {

    it('deletes the caller’s own agent', function (): void {
        $agent = createAgentForUser($this->user);

        $this->withHeaders(authHeaders($this->token))
            ->deleteJson("/api/v1/agents/{$agent->external_id}")
            ->assertNoContent();

        expect(Agent::whereKey($agent->id)->exists())->toBeFalse();
    });

    it('hides another account’s agent from delete', function (): void {
        $otherUser = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $otherAgent = createAgentForUser($otherUser);

        $this->withHeaders(authHeaders($this->token))
            ->deleteJson("/api/v1/agents/{$otherAgent->external_id}")
            ->assertNotFound();

        expect(Agent::whereKey($otherAgent->id)->exists())->toBeTrue();
    });
});

describe('Account-level MCPs + attach/detach', function (): void {

    it('lists only custom (non-connector) MCPs at the account level', function (): void {
        AgentMcpServer::create([
            'workspace_id' => $this->user->workspace()->id,
            'name' => 'Custom one',
            'slug' => 'custom_one',
            'url' => 'https://example.com/mcp',
            'transport' => 'streamable_http',
            'auth_type' => 'none',
            'connector_slug' => null,
        ]);

        AgentMcpServer::create([
            'workspace_id' => $this->user->workspace()->id,
            'name' => 'Built-in Slack',
            'slug' => 'slack',
            'url' => 'https://slack.example.com',
            'transport' => 'streamable_http',
            'auth_type' => 'none',
            'connector_slug' => 'slack',
        ]);

        $this->withHeaders(authHeaders($this->token))
            ->getJson('/api/v1/mcps')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Custom one');
    });

    it('attaches an account MCP to an agent then detaches it', function (): void {
        $agent = createAgentForUser($this->user);

        $mcp = AgentMcpServer::create([
            'workspace_id' => $this->user->workspace()->id,
            'name' => 'Linear',
            'slug' => 'linear',
            'url' => 'https://mcp.linear.app',
            'transport' => 'streamable_http',
            'auth_type' => 'none',
            'connector_slug' => null,
        ]);

        $this->withHeaders(authHeaders($this->token))
            ->postJson("/api/v1/agents/{$agent->external_id}/mcps", ['mcp_id' => $mcp->external_id])
            ->assertCreated();

        $this->withHeaders(authHeaders($this->token))
            ->getJson("/api/v1/agents/{$agent->external_id}/mcps")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Linear');

        $this->withHeaders(authHeaders($this->token))
            ->deleteJson("/api/v1/agents/{$agent->external_id}/mcps/{$mcp->external_id}")
            ->assertNoContent();

        $this->withHeaders(authHeaders($this->token))
            ->getJson("/api/v1/agents/{$agent->external_id}/mcps")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('attaches a catalog connector by id (not just custom servers)', function (): void {
        $agent = createAgentForUser($this->user);

        // A catalog-backed server (connector_slug set). The attach endpoint
        // accepts any server connected to the account, not only custom ones.
        $mcp = AgentMcpServer::create([
            'workspace_id' => $this->user->workspace()->id,
            'name' => 'Weather',
            'slug' => 'weather',
            'url' => 'https://chatvia.test/mcp/weather',
            'transport' => 'streamable_http',
            'auth_type' => 'none',
            'connector_slug' => 'weather',
        ]);

        $this->withHeaders(authHeaders($this->token))
            ->postJson("/api/v1/agents/{$agent->external_id}/mcps", ['mcp_id' => $mcp->external_id])
            ->assertCreated();

        $this->withHeaders(authHeaders($this->token))
            ->getJson("/api/v1/agents/{$agent->external_id}/mcps")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Weather');
    });

    it('rejects attaching another account’s MCP server or function', function (): void {
        $agent = createAgentForUser($this->user);
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));

        $theirMcp = AgentMcpServer::create([
            'workspace_id' => $other->workspace()->id,
            'name' => 'Theirs',
            'slug' => 'theirs',
            'url' => 'https://mcp.example.test',
            'transport' => 'streamable_http',
            'auth_type' => 'none',
        ]);
        $theirFunction = AgentFunction::query()->create([
            'workspace_id' => $other->workspace()->id,
            'internal_name' => 'theirs',
            'description' => 'Not yours',
            'parameters_schema' => ['type' => 'object', 'properties' => []],
            'webhook_url' => 'https://example.com/hook',
            'webhook_method' => 'POST',
        ]);

        $this->withHeaders(authHeaders($this->token))
            ->postJson("/api/v1/agents/{$agent->external_id}/mcps", ['mcp_id' => $theirMcp->external_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mcp_id']);

        $this->withHeaders(authHeaders($this->token))
            ->postJson("/api/v1/agents/{$agent->external_id}/functions", ['function_id' => $theirFunction->external_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['function_id']);

        // A malformed id fails validation too, before any database lookup.
        $this->withHeaders(authHeaders($this->token))
            ->postJson("/api/v1/agents/{$agent->external_id}/mcps", ['mcp_id' => 'not-an-id'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mcp_id']);

        expect($agent->mcpServers()->count())->toBe(0)
            ->and($agent->functions()->count())->toBe(0);
    });
});

describe('Account-level MCPs', function (): void {

    it('lists and deletes a custom MCP (created in the dashboard, not via the API)', function (): void {
        $headers = authHeaders($this->token);

        $mcp = AgentMcpServer::create([
            'workspace_id' => $this->user->workspace()->id,
            'name' => 'Linear',
            'slug' => 'linear',
            'url' => 'https://mcp.linear.app',
            'transport' => 'streamable_http',
            'auth_type' => 'bearer',
            'auth_value' => 'lin_api_xxx',
            'connector_slug' => null,
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/mcps')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Linear');

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/mcps/'.$mcp->external_id)
            ->assertNoContent();

        $this->withHeaders($headers)
            ->getJson('/api/v1/mcps')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});

describe('Account-level Functions CRUD', function (): void {

    it('creates and deletes an account function via /api/v1/functions', function (): void {
        $headers = authHeaders($this->token);

        $created = $this->withHeaders($headers)
            ->postJson('/api/v1/functions', [
                'internal_name' => 'get_order_status',
                'description' => 'Look up an order by id',
                'parameters_schema' => ['type' => 'object', 'properties' => (object) []],
                'webhook_url' => 'https://acme.example.com/webhooks/orders',
            ])
            ->assertCreated()
            ->json('data');

        expect($created['internal_name'])->toBe('get_order_status');
        expect($created['webhook']['method'])->toBe('POST');

        $this->withHeaders($headers)
            ->getJson('/api/v1/functions')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/functions/'.$created['id'])
            ->assertNoContent();
    });
});

describe('POST /api/v1/chatters/sign', function (): void {

    it('returns a ready-to-open url for a chatter', function (): void {
        $agent = createAgentForUser($this->user);
        $endUser = app(CreateChatterAction::class)->execute(new CreateChatterData($this->user->workspace(), $agent, 'Jane'));

        $response = $this->withHeaders(authHeaders($this->token))
            ->postJson('/api/v1/chatters/sign', [
                'agent_id' => $agent->external_id,
                'user_id' => $endUser->external_id,
                'ttl_seconds' => 600,
            ]);

        $response->assertCreated()->assertJsonStructure(['data' => ['url', 'expires_at']]);

        $url = $response->json('data.url');
        expect($url)->toContain('?token=');
        $token = explode('?token=', $url)[1];

        // The minted token resolves to this agent + end-user. Refresh first so we
        // read the secret that minting lazily provisioned on the account, as a
        // separate verifying request would — not the stale pre-mint relation
        // cached on this instance.
        $agent->refresh();
        expect(app(AgentSignInTokenService::class)->verify($token, $agent)?->id)
            ->toBe($endUser->id);
    });

    it('requires an agent_id and user_id', function (): void {
        $this->withHeaders(authHeaders($this->token))
            ->postJson('/api/v1/chatters/sign', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agent_id', 'user_id']);
    });

    it('rejects ttl_seconds beyond the max', function (): void {
        $agent = createAgentForUser($this->user);
        $endUser = app(CreateChatterAction::class)->execute(new CreateChatterData($this->user->workspace(), $agent, 'Jane'));

        $this->withHeaders(authHeaders($this->token))
            ->postJson('/api/v1/chatters/sign', [
                'agent_id' => $agent->external_id,
                'user_id' => $endUser->external_id,
                'ttl_seconds' => 99999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ttl_seconds']);
    });

    it('404s when minting a link for another account’s agent', function (): void {
        $otherUser = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $otherAgent = createAgentForUser($otherUser);

        $this->withHeaders(authHeaders($this->token))
            ->postJson('/api/v1/chatters/sign', ['agent_id' => $otherAgent->external_id, 'user_id' => 'x'])
            ->assertNotFound();
    });
});

describe('AgentSignInTokenService', function (): void {

    it('issues a token resolving to the agent + user, multi-use until expiry', function (): void {
        $agent = createAgentForUser($this->user);
        $endUser = app(CreateChatterAction::class)->execute(new CreateChatterData($this->user->workspace(), $agent, 'Jane'));
        $service = app(AgentSignInTokenService::class);

        $token = $service->issue($endUser, $agent)['token'];

        // Multi-use: a repeat lookup still resolves to the same end-user
        // (the token is not consumed).
        expect($service->verify($token, $agent)?->id)->toBe($endUser->id);
        expect($service->verify($token, $agent)?->id)->toBe($endUser->id);
    });

    it('returns null for an unknown token', function (): void {
        $agent = createAgentForUser($this->user);

        expect(app(AgentSignInTokenService::class)->verify('nope', $agent))->toBeNull();
    });
});

/**
 * Test helper — creates an agent in the user's primary team.
 */
function createAgentForUser(User $user, string $name = 'Test Agent'): Agent
{
    return createAgentInTeam($user, $user->primaryTeam()->id, $name);
}

function createAgentInTeam(User $user, int $teamId, string $name = 'Test Agent'): Agent
{
    return Agent::create([
        'workspace_id' => $user->workspace()->id,
        'team_id' => $teamId,
        'name' => $name,
        'external_id' => Str::random(14),
        'slug' => Str::random(14),
        'is_public' => false,
        'source' => 'api',
    ]);
}
