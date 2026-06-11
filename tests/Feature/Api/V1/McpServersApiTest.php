<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;

beforeEach(function (): void {
    // UserObserver::created auto-provisions the default team + billing + quota.
    $this->user = User::factory()->create();
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

    it('lists the workspace owner’s custom servers for a member token', function (): void {
        $custom = makeCustomMcpServer($this->user);

        $member = User::factory()->employee($this->user)->create();
        $memberToken = $member->createToken('account')->plainTextToken;

        $this->withHeaders(mcpHeaders($memberToken))
            ->getJson('/api/v1/mcps')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $custom->external_id);
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
