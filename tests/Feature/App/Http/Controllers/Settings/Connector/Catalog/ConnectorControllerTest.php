<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * The Connectors page's data (ConnectorController@index): connectors belong to
 * the workspace, so a teammate with account.connectors sees the owner's, each
 * with the number of agents it is attached to.
 */
it('lists the workspace\'s connectors to a teammate, with the count of attached agents', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $server = AgentMcpServer::factory()->attachedTo($agent)->create(['slug' => 'crm']);
    $teammate = teammateWithPermissions($owner, ['account.connectors']);

    $this->actingAs($teammate)
        ->getJson(route('integrations.connectors.data'))
        ->assertOk()
        ->assertJsonPath('custom.0.server.external_id', $server->external_id)
        ->assertJsonPath('custom.0.server.agents_count', 1);
});
