<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Membership\Team\Services\TeamService;

/**
 * The account API for MCP servers: the workspace's connected servers (custom ones added
 * by URL and connected catalog connectors); custom ones can be created and deleted here, built-ins never appear.
 */
beforeEach(function (): void {
    // The account-level routes are gated by team permissions, so the owner
    // needs their default team to be recognised as an owner (ownsTeam → bypass).
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->token = $this->user->createToken('account')->plainTextToken;
});

describe('GET /api/v1/mcps', function (): void {

    it('lists the workspace’s servers — custom and connected catalog — with their kind', function (): void {
        $custom = AgentMcpServer::factory()->forOwner($this->user)->create();
        $catalog = AgentMcpServer::factory()->forOwner($this->user)->preset('github')->create();

        $list = $this->withToken($this->token)
            ->getJson('/api/v1/mcps')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $kinds = collect($list->json('data'))->pluck('kind', 'id');
        expect($kinds[$custom->external_id])->toBe('custom')
            ->and($kinds[$catalog->external_id])->toBe('catalog');
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

    it('returns the workspace’s own catalog connector but 404s a built-in one', function (): void {
        $catalog = AgentMcpServer::factory()->forOwner($this->user)->preset('github')->create();
        $builtin = AgentMcpServer::factory()->builtin('weather')->create(['workspace_id' => null]);

        $this->withToken($this->token)
            ->getJson("/api/v1/mcps/{$catalog->external_id}")
            ->assertOk()
            ->assertJsonPath('data.kind', 'catalog');

        $this->withToken($this->token)
            ->getJson("/api/v1/mcps/{$builtin->external_id}")
            ->assertNotFound();
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
