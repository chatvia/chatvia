<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Public API — permission parity
|--------------------------------------------------------------------------
|
| The public API enforces the SAME permission model as the dashboard:
|   • the workspace owner has full access (owners bypass every gate);
|   • a teammate is limited to exactly the permissions their role grants;
|   • a teammate WITHOUT the relevant permission can never issue the request.
|
| Every test contrasts owner / member-without / member-with so the gate under
| test is the permission — not tenancy or agent access. This is the API half of
| the production sign-off: "a team member without the permission will never be
| able to issue a certain request."
*/

beforeEach(function (): void {
    // Deleting a chatter wipes its avatar tree on the public bucket; fake the
    // disk so the delete tests stay hermetic and never reach real S3.
    Storage::fake('public');

    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->ownerToken = $this->owner->createToken('account')->plainTextToken;
    $this->agent = createAgent($this->owner);
});

function permHeaders(string $token): array
{
    // Reset the auth guard before each request. Laravel reuses one app (and one
    // sanctum guard) across requests in a single test, caching the FIRST resolved
    // user — so a second identity in the same test would otherwise be silently
    // evaluated as the first. Called right before every request via withHeaders().
    // Production resolves the user per request, so this only affects tests.
    app('auth')->forgetGuards();

    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

/**
 * A teammate of $owner, attached to their team under a role granting exactly
 * $permissions (all-agents access by default). `current_team_id` is set so the
 * permission helpers resolve the right team for a token-only (sessionless)
 * request. Returns the bearer token minted in the teammate's name.
 *
 * @param  list<string>  $permissions
 * @param  list<string>  $agentIds  external ids when $allAgents is false
 */
function permMemberToken(User $owner, array $permissions, bool $allAgents = true, array $agentIds = []): string
{
    $team = $owner->primaryTeam();

    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Role-'.Str::random(10),
        permissions: $permissions,
        allAgents: $allAgents,
        agentExternalIds: $agentIds,
    ));

    $member = User::factory()->employee($owner)->create(['current_team_id' => $team->id]);
    $team->members()->attach($member->id, ['role_id' => $role->id]);

    // Spatie caches the permission→role map for the whole process. We create a
    // role and hit it in the same test, so reset the cache or the next request
    // resolves against a stale map (production reloads per request — not a bug).
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $member->createToken('account')->plainTextToken;
}

function permMakeMcp(User $owner): AgentMcpServer
{
    return AgentMcpServer::create([
        'user_id' => $owner->id,
        'connector_slug' => null,
        'name' => 'Server '.Str::random(6),
        'slug' => Str::random(10),
        'url' => 'https://mcp.example.test',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
}

// ---------------------------------------------------------------------------
// Account-level resources — gated by team.permission:account.*
// ---------------------------------------------------------------------------

describe('account-level functions (account.functions)', function (): void {

    it('lets the owner read and the gate is open to write', function (): void {
        $this->withHeaders(permHeaders($this->ownerToken))->getJson('/api/v1/functions')->assertOk();
        // owner passes the gate, then hits validation on an empty body
        $this->withHeaders(permHeaders($this->ownerToken))->postJson('/api/v1/functions', [])->assertStatus(422);
    });

    it('forbids a member WITHOUT account.functions (read AND write)', function (): void {
        $token = permMemberToken($this->owner, []);

        $this->withHeaders(permHeaders($token))->getJson('/api/v1/functions')->assertForbidden();
        $this->withHeaders(permHeaders($token))->postJson('/api/v1/functions', [
            'internal_name' => 'x', 'description' => 'y', 'webhook_url' => 'https://e.test/x',
        ])->assertForbidden();
    });

    it('lets a member WITH account.functions through the gate', function (): void {
        $token = permMemberToken($this->owner, ['account.functions']);

        $this->withHeaders(permHeaders($token))->getJson('/api/v1/functions')->assertOk();
        // passes the gate, then fails validation — proves the 403 is gone
        $this->withHeaders(permHeaders($token))->postJson('/api/v1/functions', [])->assertStatus(422);
    });
});

describe('account-level MCP servers (account.connectors)', function (): void {

    it('lets the owner list and delete', function (): void {
        $mcp = permMakeMcp($this->owner);

        $this->withHeaders(permHeaders($this->ownerToken))->getJson('/api/v1/mcps')->assertOk();
        $this->withHeaders(permHeaders($this->ownerToken))->deleteJson("/api/v1/mcps/{$mcp->external_id}")->assertNoContent();
    });

    it('forbids a member WITHOUT account.connectors (list AND delete)', function (): void {
        $mcp = permMakeMcp($this->owner);
        $token = permMemberToken($this->owner, []);

        $this->withHeaders(permHeaders($token))->getJson('/api/v1/mcps')->assertForbidden();
        $this->withHeaders(permHeaders($token))->deleteJson("/api/v1/mcps/{$mcp->external_id}")->assertForbidden();

        expect(AgentMcpServer::whereKey($mcp->id)->exists())->toBeTrue();
    });

    it('lets a member WITH account.connectors through the gate', function (): void {
        $mcp = permMakeMcp($this->owner);
        $token = permMemberToken($this->owner, ['account.connectors']);

        $this->withHeaders(permHeaders($token))->getJson('/api/v1/mcps')->assertOk();
        $this->withHeaders(permHeaders($token))->deleteJson("/api/v1/mcps/{$mcp->external_id}")->assertNoContent();
    });
});

describe('account-level chatters (account.chatters.manage)', function (): void {

    $chatterBody = fn (Agent $agent): array => [
        'name' => 'Jane', 'agent_ids' => [$agent->external_id],
        'email' => 'jane'.Str::random(6).'@example.test', 'password' => 'super-secret-pw',
    ];

    it('lets the owner create a chatter', function () use ($chatterBody): void {
        $this->withHeaders(permHeaders($this->ownerToken))
            ->postJson('/api/v1/chatters', $chatterBody($this->agent))
            ->assertOk();
    });

    it('forbids a member WITHOUT account.chatters.manage', function () use ($chatterBody): void {
        $token = permMemberToken($this->owner, []);

        $this->withHeaders(permHeaders($token))
            ->postJson('/api/v1/chatters', $chatterBody($this->agent))
            ->assertForbidden();
    });

    it('lets a member WITH account.chatters.manage create a chatter', function () use ($chatterBody): void {
        $token = permMemberToken($this->owner, ['account.chatters.manage']);

        $this->withHeaders(permHeaders($token))
            ->postJson('/api/v1/chatters', $chatterBody($this->agent))
            ->assertOk();
    });

    it('forbids a member WITHOUT account.chatters.manage from deleting a chatter', function (): void {
        $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData($this->owner, $this->agent, 'Jane'));
        $token = permMemberToken($this->owner, []);

        $this->withHeaders(permHeaders($token))
            ->deleteJson('/api/v1/chatters/'.$chatter->external_id)
            ->assertForbidden();

        expect(User::whereKey($chatter->id)->exists())->toBeTrue();
    });

    it('lets a member WITH account.chatters.manage delete a chatter', function (): void {
        $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData($this->owner, $this->agent, 'Jane'));
        $token = permMemberToken($this->owner, ['account.chatters.manage']);

        $this->withHeaders(permHeaders($token))
            ->deleteJson('/api/v1/chatters/'.$chatter->external_id)
            ->assertNoContent();
    });

    it('forbids a member WITHOUT account.chatters.manage from revoking agent access', function (): void {
        $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData($this->owner, $this->agent, 'Jane'));
        $token = permMemberToken($this->owner, []);

        $this->withHeaders(permHeaders($token))
            ->deleteJson('/api/v1/chatters/'.$chatter->external_id.'/agents', ['agent_ids' => [$this->agent->external_id]])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Owner-only operations — gated by api.owner (no delegable permission)
// ---------------------------------------------------------------------------

describe('owner-only operations (api.owner)', function (): void {

    it('forbids a member from creating an agent — even with broad agent perms', function (): void {
        $token = permMemberToken($this->owner, ['agent.setup', 'agent.delete']);

        $this->withHeaders(permHeaders($token))
            ->postJson('/api/v1/agents', ['name' => 'Sneaky', 'team_id' => $this->owner->primaryTeam()->external_id, 'is_public' => false])
            ->assertForbidden();
    });

    it('lets the owner create an agent', function (): void {
        $this->withHeaders(permHeaders($this->ownerToken))
            ->postJson('/api/v1/agents', ['name' => 'Support Bot', 'team_id' => $this->owner->primaryTeam()->external_id, 'is_public' => false])
            ->assertOk();
    });

    it('forbids a member from listing or creating teams', function (): void {
        $token = permMemberToken($this->owner, ['account.chatters.manage', 'agent.setup']);

        $this->withHeaders(permHeaders($token))->getJson('/api/v1/teams')->assertForbidden();
        $this->withHeaders(permHeaders($token))->postJson('/api/v1/teams', ['name' => 'X'])->assertForbidden();
    });

    it('lets the owner list teams', function (): void {
        $this->withHeaders(permHeaders($this->ownerToken))->getJson('/api/v1/teams')->assertOk();
    });
});

// ---------------------------------------------------------------------------
// Per-agent operations — gated by can.access.agent + agent.permission:*
// ---------------------------------------------------------------------------

describe('per-agent: update is gated by agent.setup', function (): void {

    it('owner and member-with pass; member-without is forbidden', function (): void {
        $uri = "/api/v1/agents/{$this->agent->external_id}";

        $this->withHeaders(permHeaders(permMemberToken($this->owner, [])))
            ->patchJson($uri, ['is_public' => true])->assertForbidden();

        $this->withHeaders(permHeaders(permMemberToken($this->owner, ['agent.setup'])))
            ->patchJson($uri, ['is_public' => true])->assertOk();

        $this->withHeaders(permHeaders($this->ownerToken))
            ->patchJson($uri, ['is_public' => true])->assertOk();
    });
});

describe('per-agent: delete is gated by agent.delete', function (): void {

    it('forbids a member who has agent.setup but not agent.delete', function (): void {
        // The contrast with agent.setup proves it's specifically agent.delete.
        $token = permMemberToken($this->owner, ['agent.setup']);

        $this->withHeaders(permHeaders($token))
            ->deleteJson("/api/v1/agents/{$this->agent->external_id}")
            ->assertForbidden();

        expect(Agent::whereKey($this->agent->id)->exists())->toBeTrue();
    });

    it('lets a member WITH agent.delete delete the agent', function (): void {
        $token = permMemberToken($this->owner, ['agent.delete']);

        $this->withHeaders(permHeaders($token))
            ->deleteJson("/api/v1/agents/{$this->agent->external_id}")
            ->assertNoContent();

        expect(Agent::whereKey($this->agent->id)->exists())->toBeFalse();
    });

    it('lets the owner delete the agent', function (): void {
        $this->withHeaders(permHeaders($this->ownerToken))
            ->deleteJson("/api/v1/agents/{$this->agent->external_id}")
            ->assertNoContent();
    });
});

describe('per-agent: training + chat are gated by their permission', function (): void {

    it('gates file training by agent.sources.train.files', function (): void {
        $uri = "/api/v1/agents/{$this->agent->external_id}/sources/files";

        $this->withHeaders(permHeaders(permMemberToken($this->owner, [])))
            ->postJson($uri, [])->assertForbidden();

        // with the permission the gate opens; the empty body then fails validation
        $this->withHeaders(permHeaders(permMemberToken($this->owner, ['agent.sources.train.files'])))
            ->postJson($uri, [])->assertStatus(422);
    });

    it('gates MCP attach by agent.sources.train.connectors', function (): void {
        $uri = "/api/v1/agents/{$this->agent->external_id}/mcps";

        $this->withHeaders(permHeaders(permMemberToken($this->owner, [])))
            ->postJson($uri, [])->assertForbidden();

        $this->withHeaders(permHeaders(permMemberToken($this->owner, ['agent.sources.train.connectors'])))
            ->postJson($uri, [])->assertStatus(422);
    });

    it('gates conversation history by agent.chat.view', function (): void {
        $uri = "/api/v1/agents/{$this->agent->external_id}/conversations";

        $this->withHeaders(permHeaders(permMemberToken($this->owner, [])))
            ->getJson($uri)->assertForbidden();

        $this->withHeaders(permHeaders(permMemberToken($this->owner, ['agent.chat.view'])))
            ->getJson($uri)->assertOk();

        $this->withHeaders(permHeaders($this->ownerToken))->getJson($uri)->assertOk();
    });

    it('lets any member who can access the agent read it (no extra permission)', function (): void {
        // Reads (show, source list) need access but no specific permission —
        // matching the dashboard's open agent read pages.
        $token = permMemberToken($this->owner, []);

        $this->withHeaders(permHeaders($token))
            ->getJson("/api/v1/agents/{$this->agent->external_id}")->assertOk();
        $this->withHeaders(permHeaders($token))
            ->getJson("/api/v1/agents/{$this->agent->external_id}/sources")->assertOk();
    });
});

// ---------------------------------------------------------------------------
// Agent-access isolation + tenancy — gated by can.access.agent
// ---------------------------------------------------------------------------

describe('agent access isolation + tenancy', function (): void {

    it('forbids a member from an agent their role does not grant', function (): void {
        $granted = $this->agent;
        $ungranted = createAgent($this->owner, ['name' => 'Other', 'external_id' => Str::random(20), 'slug' => Str::random(14)]);

        // Access to ONLY the granted agent, with the chat permission.
        $token = permMemberToken($this->owner, ['agent.chat.view'], allAgents: false, agentIds: [$granted->external_id]);

        $this->withHeaders(permHeaders($token))
            ->getJson("/api/v1/agents/{$granted->external_id}/conversations")->assertOk();

        // Same permission, but no access to this agent → blocked at can.access.agent.
        $this->withHeaders(permHeaders($token))
            ->getJson("/api/v1/agents/{$ungranted->external_id}/conversations")->assertForbidden();
        $this->withHeaders(permHeaders($token))
            ->getJson("/api/v1/agents/{$ungranted->external_id}")->assertForbidden();
    });

    it('forbids reaching another workspace’s agent (owner token)', function (): void {
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $otherAgent = createAgent($other);

        $this->withHeaders(permHeaders($this->ownerToken))
            ->getJson("/api/v1/agents/{$otherAgent->external_id}")->assertForbidden();
        $this->withHeaders(permHeaders($this->ownerToken))
            ->patchJson("/api/v1/agents/{$otherAgent->external_id}", ['is_public' => true])->assertForbidden();
    });
});
