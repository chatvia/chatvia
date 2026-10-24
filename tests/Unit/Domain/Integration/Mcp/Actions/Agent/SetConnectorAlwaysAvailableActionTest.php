<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Actions\Agent\SetConnectorAlwaysAvailableAction;
use Domain\Integration\Mcp\Data\Agent\SetConnectorAlwaysAvailableData;
use Domain\Integration\Mcp\Exceptions\ConnectorNotAttachedException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Support\Facades\DB;

/**
 * An attached connector can be marked always-on for an agent, so its tools
 * skip tool search; any number may be, and only an attached one can.
 */

/** The agent's `always_available` flag for the server, read from the pivot. */
function saaAlwaysAvailableOf(Agent $agent, AgentMcpServer $server): bool
{
    return (bool) DB::table('agent_mcp_server')->where('agent_id', $agent->id)->where('mcp_server_id', $server->id)->value('always_available');
}

beforeEach(function (): void {
    $this->agent = createAgent(User::factory()->create());
});

it('marks an attached connector always-on and back off', function (): void {
    $server = AgentMcpServer::factory()->attachedTo($this->agent)->create();
    $action = new SetConnectorAlwaysAvailableAction;

    $action->execute($this->agent, $server, new SetConnectorAlwaysAvailableData(always_available: true));
    expect(saaAlwaysAvailableOf($this->agent, $server))->toBeTrue();

    $action->execute($this->agent, $server, new SetConnectorAlwaysAvailableData(always_available: false));
    expect(saaAlwaysAvailableOf($this->agent, $server))->toBeFalse();
});

it('lets any number of connectors be always-on together', function (): void {
    $crm = AgentMcpServer::factory()->attachedTo($this->agent, alwaysAvailable: true)->create();
    $server = AgentMcpServer::factory()->attachedTo($this->agent)->create();

    (new SetConnectorAlwaysAvailableAction)->execute($this->agent, $server, new SetConnectorAlwaysAvailableData(always_available: true));

    expect(saaAlwaysAvailableOf($this->agent, $crm))->toBeTrue()
        ->and(saaAlwaysAvailableOf($this->agent, $server))->toBeTrue();
});

it('refuses a connector that is not attached to the agent', function (): void {
    $server = AgentMcpServer::factory()->create(['workspace_id' => $this->agent->workspace_id]);

    expect(fn () => (new SetConnectorAlwaysAvailableAction)->execute($this->agent, $server, new SetConnectorAlwaysAvailableData(always_available: true)))
        ->toThrow(ConnectorNotAttachedException::class, 'not attached');
});
