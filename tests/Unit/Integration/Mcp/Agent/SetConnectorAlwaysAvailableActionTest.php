<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Actions\Agent\SetConnectorAlwaysAvailableAction;
use Domain\Integration\Mcp\Data\Agent\SetConnectorAlwaysAvailableData;
use Domain\Integration\Mcp\Exceptions\ConnectorNotAttachedException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Support\Facades\DB;

function saaServer(Agent $agent, string $slug, bool $attach = true, bool $alwaysAvailable = false): AgentMcpServer
{
    $server = AgentMcpServer::create([
        'workspace_id' => $agent->workspace_id,
        'name' => ucfirst($slug),
        'slug' => $slug,
        'url' => "https://example.test/mcp/{$slug}",
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);

    if ($attach) {
        DB::table('agent_mcp_server')->insert(['agent_id' => $agent->id, 'mcp_server_id' => $server->id, 'always_available' => $alwaysAvailable]);
    }

    return $server;
}

function alwaysAvailableOf(Agent $agent, AgentMcpServer $server): bool
{
    return (bool) DB::table('agent_mcp_server')->where('agent_id', $agent->id)->where('mcp_server_id', $server->id)->value('always_available');
}

beforeEach(function (): void {
    $this->agent = createAgent(User::factory()->create());
});

it('marks an attached connector always-on and back off', function (): void {
    $server = saaServer($this->agent, 'tutor');
    $action = new SetConnectorAlwaysAvailableAction;

    $action->execute($this->agent, $server, new SetConnectorAlwaysAvailableData(always_available: true));
    expect(alwaysAvailableOf($this->agent, $server))->toBeTrue();

    $action->execute($this->agent, $server, new SetConnectorAlwaysAvailableData(always_available: false));
    expect(alwaysAvailableOf($this->agent, $server))->toBeFalse();
});

it('lets any number of connectors be always-on together', function (): void {
    $crm = saaServer($this->agent, 'crm', alwaysAvailable: true);
    $server = saaServer($this->agent, 'tutor');

    (new SetConnectorAlwaysAvailableAction)->execute($this->agent, $server, new SetConnectorAlwaysAvailableData(always_available: true));

    expect(alwaysAvailableOf($this->agent, $crm))->toBeTrue()
        ->and(alwaysAvailableOf($this->agent, $server))->toBeTrue();
});

it('refuses a connector that is not attached to the agent', function (): void {
    $server = saaServer($this->agent, 'tutor', attach: false);

    expect(fn () => (new SetConnectorAlwaysAvailableAction)->execute($this->agent, $server, new SetConnectorAlwaysAvailableData(always_available: true)))
        ->toThrow(ConnectorNotAttachedException::class, 'not attached');
});
