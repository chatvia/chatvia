<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Illuminate\Support\Facades\DB;

/**
 * The "always on" switch of a connector attached to an agent: its tools go
 * out on every turn, without the relevance routing picking them.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->agent = makeTeamAgent($this->user, 'Tutor Bot');

    $this->server = AgentMcpServer::factory()->forOwner($this->user)->create([
        'name' => 'Hejly AI Tutor',
        'slug' => 'hejly-tutor',
        'url' => 'https://hejly.test/mcp/tutor',
        'auth_type' => 'bearer',
    ]);
    $this->agent->attachConnector($this->server);
    app(ToolRegistry::class)->forget($this->agent->id);
});

it('marks an attached connector always-on and reports it on the connectors list', function (): void {
    McpTool::factory()->count(3)->allowed()->create(['mcp_server_id' => $this->server->id]);

    $this->actingAs($this->user)
        ->putJson(route('agents.sources.connectors.always-available', ['agent' => $this->agent->external_id, 'slug' => 'hejly-tutor']), ['always_available' => true])
        ->assertOk()
        ->assertJsonPath('always_available', true);

    expect(DB::table('agent_mcp_server')->where('agent_id', $this->agent->id)->value('always_available'))->toBeTruthy();

    $this->actingAs($this->user)
        ->getJson(route('agents.sources.connectors.data', $this->agent->external_id))
        ->assertOk()
        ->assertJsonPath('custom.0.slug', 'hejly-tutor')
        ->assertJsonPath('custom.0.is_attached', true)
        ->assertJsonPath('custom.0.always_available', true);
});

it('turns always-on on however many tools the connector has', function (): void {
    McpTool::factory()->count(25)->allowed()->create(['mcp_server_id' => $this->server->id]);

    $this->actingAs($this->user)
        ->putJson(route('agents.sources.connectors.always-available', ['agent' => $this->agent->external_id, 'slug' => 'hejly-tutor']), ['always_available' => true])
        ->assertOk()
        ->assertJsonPath('always_available', true);

    expect(DB::table('agent_mcp_server')->where('agent_id', $this->agent->id)->value('always_available'))->toBeTruthy();
});

it('validates the flag', function (): void {
    $this->actingAs($this->user)
        ->putJson(route('agents.sources.connectors.always-available', ['agent' => $this->agent->external_id, 'slug' => 'hejly-tutor']), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['always_available']);
});

it('404s for a connector of the workspace that is not attached to the agent', function (): void {
    $unattached = AgentMcpServer::factory()->forOwner($this->user)->create(['slug' => 'not-attached']);

    $this->actingAs($this->user)
        ->putJson(route('agents.sources.connectors.always-available', ['agent' => $this->agent->external_id, 'slug' => $unattached->slug]), ['always_available' => true])
        ->assertNotFound();

    expect($this->agent->mcpServers()->whereKey($unattached->id)->exists())->toBeFalse();
});
