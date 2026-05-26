<?php

use App\Auth\Models\User;
use App\Auth\Services\AgentJwtVerifier;
use App\Teams\Services\TeamService;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);

    [$this->masterToken, $this->masterPlain] = (function () {
        $token = $this->user->createToken('master', ['agents:manage']);

        return [$token->accessToken, $token->plainTextToken];
    })();
});

function authHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

describe('POST /api/v1/agents', function (): void {

    it('creates a knowledge-base agent and returns the api token', function (): void {
        $response = $this->withHeaders(authHeaders($this->masterPlain))
            ->postJson('/api/v1/agents', ['name' => 'Support Bot']);

        $response->assertCreated()
            ->assertJsonPath('agent.name', 'Support Bot')
            ->assertJsonPath('agent.type', 'knowledge_base')
            ->assertJsonPath('agent.created_via', 'api')
            ->assertJsonStructure([
                'agent' => ['id', 'url', 'chat_url', 'embed_url'],
                'token' => ['id', 'plaintext', 'abilities'],
            ])
            ->assertJsonMissing(['jwt_secret']);
    });

    it('rejects requests with no master ability', function (): void {
        $agentToken = createAgentForUser($this->user)->createToken('agent', ['chat:send'])->plainTextToken;

        $this->withHeaders(authHeaders($agentToken))
            ->postJson('/api/v1/agents', ['name' => 'x'])
            ->assertForbidden();
    });

    it('rejects unauthenticated requests', function (): void {
        $this->postJson('/api/v1/agents', ['name' => 'x'])->assertUnauthorized();
    });

    it('validates name', function (): void {
        $this->withHeaders(authHeaders($this->masterPlain))
            ->postJson('/api/v1/agents', ['name' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });
});

describe('PATCH /api/v1/agents/{agent}', function (): void {

    it('lets the master token patch metadata + flags', function (): void {
        $agent = createAgentForUser($this->user);

        $this->withHeaders(authHeaders($this->masterPlain))
            ->patchJson("/api/v1/agents/{$agent->external_id}", [
                'metadata' => ['external_user_id' => 'cus_42'],
                'analytics_enabled' => false,
                'collect_leads' => false,
            ])
            ->assertOk()
            ->assertJsonPath('agent.metadata.external_user_id', 'cus_42')
            ->assertJsonPath('agent.analytics_enabled', false)
            ->assertJsonPath('agent.collect_leads', false);
    });

    it('lets the agent token patch itself', function (): void {
        $agent = createAgentForUser($this->user);
        $agentToken = $agent->createToken('agent', ['agent:write'])->plainTextToken;

        $this->withHeaders(authHeaders($agentToken))
            ->patchJson("/api/v1/agents/{$agent->external_id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('agent.name', 'Renamed');
    });

    it('rejects an agent token patching a different agent', function (): void {
        $myAgent = createAgentForUser($this->user);
        $otherUser = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $otherAgent = createAgentForUser($otherUser);

        $myToken = $myAgent->createToken('mine', ['agent:write'])->plainTextToken;

        $this->withHeaders(authHeaders($myToken))
            ->patchJson("/api/v1/agents/{$otherAgent->external_id}", ['name' => 'evil'])
            ->assertForbidden();
    });
});

describe('Sanctum ability scoping', function (): void {

    it('blocks chat ability from creating agents', function (): void {
        $agent = createAgentForUser($this->user);
        $chatOnly = $agent->createToken('chat-only', ['chat:send'])->plainTextToken;

        $this->withHeaders(authHeaders($chatOnly))
            ->postJson('/api/v1/agents', ['name' => 'x'])
            ->assertForbidden();
    });
});

describe('Account-level MCPs + attach/detach', function (): void {

    it('lists only custom (non-connector) MCPs at the account level', function (): void {
        // Custom — exposed.
        AgentMcpServer::create([
            'user_id' => $this->user->id,
            'name' => 'Custom one',
            'slug' => 'custom_one',
            'url' => 'https://example.com/mcp',
            'transport' => 'streamable_http',
            'auth_type' => 'none',
            'connector_slug' => null,
        ]);

        // Built-in connector — never exposed via the public API.
        AgentMcpServer::create([
            'user_id' => $this->user->id,
            'name' => 'Built-in Slack',
            'slug' => 'slack',
            'url' => 'https://slack.example.com',
            'transport' => 'streamable_http',
            'auth_type' => 'none',
            'connector_slug' => 'slack',
        ]);

        $this->withHeaders(authHeaders($this->masterPlain))
            ->getJson('/api/v1/mcps')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Custom one');
    });

    it('attaches an account MCP to an agent and lists it under that agent', function (): void {
        $agent = createAgentForUser($this->user);

        $mcp = AgentMcpServer::create([
            'user_id' => $this->user->id,
            'name' => 'Linear',
            'slug' => 'linear',
            'url' => 'https://mcp.linear.app',
            'transport' => 'streamable_http',
            'auth_type' => 'none',
            'connector_slug' => null,
        ]);

        $this->withHeaders(authHeaders($this->masterPlain))
            ->postJson("/api/v1/agents/{$agent->external_id}/mcps", ['mcp_id' => $mcp->external_id])
            ->assertCreated();

        $this->withHeaders(authHeaders($this->masterPlain))
            ->getJson("/api/v1/agents/{$agent->external_id}/mcps")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Linear');

        // Detach
        $this->withHeaders(authHeaders($this->masterPlain))
            ->deleteJson("/api/v1/agents/{$agent->external_id}/mcps/{$mcp->external_id}")
            ->assertNoContent();

        $this->withHeaders(authHeaders($this->masterPlain))
            ->getJson("/api/v1/agents/{$agent->external_id}/mcps")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});

describe('POST /api/v1/jwt-secret', function (): void {

    it('generates a secret on first call and rotates on subsequent calls', function (): void {
        expect($this->user->fresh()->jwt_secret)->toBeNull();

        $first = $this->withHeaders(authHeaders($this->masterPlain))
            ->postJson('/api/v1/jwt-secret')
            ->assertCreated()
            ->json('jwt_secret');

        expect($first)->toHaveLength(64);
        expect($this->user->fresh()->jwt_secret)->toBe($first);

        $second = $this->withHeaders(authHeaders($this->masterPlain))
            ->postJson('/api/v1/jwt-secret')
            ->assertCreated()
            ->json('jwt_secret');

        expect($second)->toHaveLength(64)->not->toBe($first);
        expect($this->user->fresh()->jwt_secret)->toBe($second);
    });

    it('shows whether a secret is configured without leaking it', function (): void {
        $this->withHeaders(authHeaders($this->masterPlain))
            ->getJson('/api/v1/jwt-secret')
            ->assertOk()
            ->assertExactJson(['configured' => false]);

        $this->user->rotateJwtSecret();

        $this->withHeaders(authHeaders($this->masterPlain))
            ->getJson('/api/v1/jwt-secret')
            ->assertOk()
            ->assertExactJson(['configured' => true]);
    });

    it('forbids non-master tokens', function (): void {
        $agentToken = createAgentForUser($this->user)->createToken('t', ['chat:send'])->plainTextToken;

        $this->withHeaders(authHeaders($agentToken))
            ->postJson('/api/v1/jwt-secret')
            ->assertForbidden();
    });
});

describe('POST /api/v1/agents/{agent}/chat-tokens', function (): void {

    it('mints a JWT that the verifier accepts and returns the redirect URL', function (): void {
        $agent = createAgentForUser($this->user);
        $agentToken = $agent->createToken('t', ['chat:send'])->plainTextToken;

        $response = $this->withHeaders(authHeaders($agentToken))
            ->postJson("/api/v1/agents/{$agent->external_id}/chat-tokens", [
                'sub' => 'cus_42',
                'name' => 'Jane',
                'email' => 'jane@example.com',
                'ttl_seconds' => 600,
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'chat_url', 'expires_at']);

        expect($response->json('chat_url'))->toContain('?token='.$response->json('token'));

        $verified = app(AgentJwtVerifier::class)->verify($agent, $response->json('token'));
        expect($verified)->not->toBeNull()
            ->and($verified['sub'])->toBe('cus_42')
            ->and($verified['name'])->toBe('Jane');
    });

    it('rejects ttl_seconds beyond the max', function (): void {
        $agent = createAgentForUser($this->user);
        $token = $agent->createToken('t', ['chat:send'])->plainTextToken;

        $this->withHeaders(authHeaders($token))
            ->postJson("/api/v1/agents/{$agent->external_id}/chat-tokens", [
                'sub' => 'cus_42',
                'ttl_seconds' => 99999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ttl_seconds']);
    });

    it('requires sub', function (): void {
        $agent = createAgentForUser($this->user);
        $token = $agent->createToken('t', ['chat:send'])->plainTextToken;

        $this->withHeaders(authHeaders($token))
            ->postJson("/api/v1/agents/{$agent->external_id}/chat-tokens", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sub']);
    });

    it('forbids tokens without chat:send ability', function (): void {
        $agent = createAgentForUser($this->user);
        $readOnly = $agent->createToken('t', ['agent:read'])->plainTextToken;

        $this->withHeaders(authHeaders($readOnly))
            ->postJson("/api/v1/agents/{$agent->external_id}/chat-tokens", ['sub' => 'cus_42'])
            ->assertForbidden();
    });
});

describe('Account-level MCPs CRUD', function (): void {

    it('creates, lists, and deletes an account MCP via /api/v1/mcps', function (): void {
        $headers = authHeaders($this->masterPlain);

        $created = $this->withHeaders($headers)
            ->postJson('/api/v1/mcps', [
                'name' => 'Linear',
                'url' => 'https://mcp.linear.app',
                'auth_type' => 'bearer',
                'auth_value' => 'lin_api_xxx',
            ])
            ->assertCreated()
            ->json('mcp');

        expect($created['name'])->toBe('Linear');
        expect($created['has_auth_value'])->toBeTrue();

        $this->withHeaders($headers)
            ->getJson('/api/v1/mcps')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/mcps/'.$created['id'])
            ->assertNoContent();

        $this->withHeaders($headers)
            ->getJson('/api/v1/mcps')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});

describe('Account-level Functions CRUD', function (): void {

    it('creates and deletes an account function via /api/v1/functions', function (): void {
        $headers = authHeaders($this->masterPlain);

        $created = $this->withHeaders($headers)
            ->postJson('/api/v1/functions', [
                'name' => 'get_order_status',
                'description' => 'Look up an order by id',
                'parameters_schema' => ['type' => 'object', 'properties' => (object) []],
                'webhook_url' => 'https://acme.example.com/webhooks/orders',
            ])
            ->assertCreated()
            ->json('function');

        expect($created['name'])->toBe('get_order_status');

        $this->withHeaders($headers)
            ->getJson('/api/v1/functions')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/functions/'.$created['id'])
            ->assertNoContent();
    });
});

describe('AgentJwtVerifier', function (): void {

    it('verifies a valid HS256 token signed with the account secret', function (): void {
        $agent = createAgentForUser($this->user);

        $jwt = JWT::encode(
            ['sub' => 'cus_42', 'email' => 'e@x.com', 'exp' => now()->addMinutes(5)->timestamp],
            $this->user->fresh()->jwt_secret,
            'HS256',
        );

        $result = app(AgentJwtVerifier::class)->verify($agent, $jwt);

        expect($result)->not->toBeNull()
            ->and($result['sub'])->toBe('cus_42')
            ->and($result['email'])->toBe('e@x.com');
    });

    it('rejects a token signed with the wrong secret', function (): void {
        $agent = createAgentForUser($this->user);

        $jwt = JWT::encode(
            ['sub' => 'cus_42', 'exp' => now()->addMinutes(5)->timestamp],
            'wrong-secret-'.str_repeat('a', 60),
            'HS256',
        );

        expect(app(AgentJwtVerifier::class)->verify($agent, $jwt))->toBeNull();
    });

    it('rejects an expired token', function (): void {
        $agent = createAgentForUser($this->user);

        $jwt = JWT::encode(
            ['sub' => 'cus_42', 'exp' => now()->subSeconds(10)->timestamp],
            $this->user->fresh()->jwt_secret,
            'HS256',
        );

        expect(app(AgentJwtVerifier::class)->verify($agent, $jwt))->toBeNull();
    });
});

/**
 * Test helper — creates an agent in the user's primary team and ensures the
 * user has a jwt_secret (since JWT signing relies on the account-level secret
 * now). Matches what POST /agents + POST /jwt-secret would do.
 */
function createAgentForUser(User $user): Agent
{
    $team = $user->primaryTeam();

    if (! $user->jwt_secret) {
        $user->rotateJwtSecret();
    }

    return Agent::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => 'Test Agent',
        'type' => 'knowledge_base',
        'external_id' => Str::random(14),
        'sub_domain' => Str::random(14),
        'is_public' => false,
        'collect_leads' => true,
        'analytics_enabled' => true,
        'created_via' => 'api',
    ]);
}
