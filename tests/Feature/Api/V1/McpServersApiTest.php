<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Membership\Team\Services\TeamService;

/**
 * The account API for MCP servers: only the workspace's custom servers (added
 * by URL) are listed, shown and deleted; catalog connectors are not.
 */
beforeEach(function (): void {
    // The account-level routes are gated by team permissions, so the owner
    // needs their default team to be recognised as an owner (ownsTeam → bypass).
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->token = $this->user->createToken('account')->plainTextToken;
});

describe('GET /api/v1/mcps', function (): void {

    it('lists the account owner’s custom servers, excluding catalog connectors', function (): void {
        $custom = AgentMcpServer::factory()->forOwner($this->user)->create();
        AgentMcpServer::factory()->forOwner($this->user)->preset('github')->create();

        $this->withToken($this->token)
            ->getJson('/api/v1/mcps')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $custom->external_id);
    });
});

describe('GET /api/v1/mcps/{mcp}', function (): void {

    it('returns an owned custom server', function (): void {
        $custom = AgentMcpServer::factory()->forOwner($this->user)->create();

        $this->withToken($this->token)
            ->getJson("/api/v1/mcps/{$custom->external_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $custom->external_id);
    });

    it('404s for a catalog connector, the workspace’s own or a built-in one', function (): void {
        $catalog = AgentMcpServer::factory()->forOwner($this->user)->preset('github')->create();
        $builtin = AgentMcpServer::factory()->builtin('weather')->create(['workspace_id' => null]);

        foreach ([$catalog, $builtin] as $server) {
            $this->withToken($this->token)
                ->getJson("/api/v1/mcps/{$server->external_id}")
                ->assertNotFound();
        }
    });
});

describe('DELETE /api/v1/mcps/{mcp}', function (): void {

    it('404s without deleting for a catalog connector, the workspace’s own or a built-in one', function (): void {
        $catalog = AgentMcpServer::factory()->forOwner($this->user)->preset('github')->create();
        $builtin = AgentMcpServer::factory()->builtin('weather')->create(['workspace_id' => null]);

        foreach ([$catalog, $builtin] as $server) {
            $this->withToken($this->token)
                ->deleteJson("/api/v1/mcps/{$server->external_id}")
                ->assertNotFound();

            expect(AgentMcpServer::whereKey($server->id)->exists())->toBeTrue();
        }
    });
});
