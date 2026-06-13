<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;

beforeEach(function (): void {
    // A real workspace owner always has a team (provisioned at trial checkout).
    // The account-level routes are gated by team permissions, so the owner needs
    // their default team to be recognised as an owner (ownsTeam → bypass).
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->token = $this->user->createToken('account')->plainTextToken;
});

function mcpHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

function makeCustomMcpServer(User $owner, array $overrides = []): AgentMcpServer
{
    return AgentMcpServer::create(array_merge([
        'user_id' => $owner->id,
        'connector_slug' => null,
        'name' => 'Custom Server',
        'slug' => Str::random(10),
        'url' => 'https://mcp.example.test',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ], $overrides));
}

describe('GET /api/v1/mcps', function (): void {

    it('lists the account owner’s custom servers, excluding catalog connectors', function (): void {
        $custom = makeCustomMcpServer($this->user);
        makeCustomMcpServer($this->user, ['connector_slug' => 'github', 'name' => 'GitHub']);

        $this->withHeaders(mcpHeaders($this->token))
            ->getJson('/api/v1/mcps')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $custom->external_id);
    });

    it('forbids a member without account.connectors from listing the workspace servers', function (): void {
        makeCustomMcpServer($this->user);

        // A bare teammate (no role granting account.connectors) must be blocked —
        // the account key resolving to the owner's workspace is NOT enough.
        $member = User::factory()->employee($this->user)->create();
        $memberToken = $member->createToken('account')->plainTextToken;

        $this->withHeaders(mcpHeaders($memberToken))
            ->getJson('/api/v1/mcps')
            ->assertForbidden();
    });
});

describe('GET /api/v1/mcps/{mcp}', function (): void {

    it('returns an owned custom server', function (): void {
        $custom = makeCustomMcpServer($this->user);

        $this->withHeaders(mcpHeaders($this->token))
            ->getJson("/api/v1/mcps/{$custom->external_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $custom->external_id);
    });

    it('404s for a server belonging to another account', function (): void {
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $theirs = makeCustomMcpServer($other);

        $this->withHeaders(mcpHeaders($this->token))
            ->getJson("/api/v1/mcps/{$theirs->external_id}")
            ->assertNotFound();
    });
});

describe('DELETE /api/v1/mcps/{mcp}', function (): void {

    it('404s without deleting for a server belonging to another account', function (): void {
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $theirs = makeCustomMcpServer($other);

        $this->withHeaders(mcpHeaders($this->token))
            ->deleteJson("/api/v1/mcps/{$theirs->external_id}")
            ->assertNotFound();

        expect(AgentMcpServer::whereKey($theirs->id)->exists())->toBeTrue();
    });
});
