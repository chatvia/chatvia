<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\DB;

/** A workspace owner with an active subscription — the agent routes sit behind the billing gate. */
function workspaceOwnerWithTeam(): User
{
    $user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($user);

    return $user->refresh();
}

beforeEach(function (): void {
    $this->user = workspaceOwnerWithTeam();
    $this->agent = makeTeamAgent($this->user, 'Tutor Bot');

    $this->server = $this->user->workspace()->mcpServers()->create([
        'name' => 'Hejly AI Tutor',
        'slug' => 'hejly-tutor',
        'url' => 'https://hejly.test/mcp/tutor',
        'transport' => 'streamable_http',
        'auth_type' => 'bearer',
        'is_enabled' => true,
    ]);
    $this->agent->attachConnector($this->server);
    app(ToolRegistry::class)->forget($this->agent->id);
});

function tutorTools(AgentMcpServer $server, int $count): void
{
    foreach (range(1, $count) as $i) {
        McpTool::create([
            'mcp_server_id' => $server->id,
            'name' => "tool_{$i}",
            'description' => "Tool {$i}",
            'input_schema' => ['type' => 'object', 'properties' => []],
            'permission' => 'allow',
            'fetched_at' => now(),
        ]);
    }
}

it('marks an attached connector always-on and reports it on the connectors list', function (): void {
    tutorTools($this->server, 3);

    $this->actingAs($this->user)
        ->putJson(route('agents.sources.connectors.always_available', ['agent' => $this->agent->external_id, 'slug' => 'hejly-tutor']), ['always_available' => true])
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

it('rejects always-on when the cap of ten tools would be exceeded', function (): void {
    tutorTools($this->server, 11);

    $this->actingAs($this->user)
        ->putJson(route('agents.sources.connectors.always_available', ['agent' => $this->agent->external_id, 'slug' => 'hejly-tutor']), ['always_available' => true])
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'at most 10 tools'));

    expect(DB::table('agent_mcp_server')->where('agent_id', $this->agent->id)->value('always_available'))->toBeFalsy();
});

it('validates the flag and 404s for a connector not attached to the agent', function (): void {
    $this->actingAs($this->user)
        ->putJson(route('agents.sources.connectors.always_available', ['agent' => $this->agent->external_id, 'slug' => 'hejly-tutor']), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['always_available']);

    $this->actingAs($this->user)
        ->putJson(route('agents.sources.connectors.always_available', ['agent' => $this->agent->external_id, 'slug' => 'nope']), ['always_available' => true])
        ->assertNotFound();
});

it('forbids another workspace from touching the flag', function (): void {
    $stranger = workspaceOwnerWithTeam();

    $response = $this->actingAs($stranger)
        ->putJson(route('agents.sources.connectors.always_available', ['agent' => $this->agent->external_id, 'slug' => 'hejly-tutor']), ['always_available' => true]);

    expect($response->status())->toBeIn([403, 404])
        ->and(DB::table('agent_mcp_server')->where('agent_id', $this->agent->id)->value('always_available'))->toBeFalsy();
});
