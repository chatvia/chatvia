<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Illuminate\Support\Facades\DB;

/**
 * The "always on" switch of a connector attached to an agent: its tools go
 * out on every turn, without the relevance routing picking them, however many
 * tools it has.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Tutor Bot');

    $this->server = AgentMcpServer::factory()->forOwner($this->owner)->create(['slug' => 'hejly-tutor', 'auth_type' => 'bearer']);
    $this->agent->attachConnector($this->server);
    app(ToolRegistry::class)->forget($this->agent->id);
});

it('marks an attached connector always-on, however many tools it has', function (): void {
    McpTool::factory()->count(25)->allowed()->create(['mcp_server_id' => $this->server->id]);

    $this->actingAs($this->owner)
        ->putJson(route('agents.sources.connectors.always-available', ['agent' => $this->agent->external_id, 'slug' => 'hejly-tutor']), ['always_available' => true])
        ->assertOk()
        ->assertJsonPath('always_available', true);

    expect(DB::table('agent_mcp_server')->where('agent_id', $this->agent->id)->value('always_available'))->toBeTrue();
});
