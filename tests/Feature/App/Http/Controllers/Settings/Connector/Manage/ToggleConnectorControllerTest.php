<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Switching a connector off or on workspace-wide (ToggleConnectorController),
 * found by its slug inside the workspace, so a teammate with
 * account.connectors switches the owner's connector.
 */
it('lets a teammate switch the owner\'s connector off by its slug', function (): void {
    $owner = User::factory()->create();
    $server = AgentMcpServer::factory()->forOwner($owner)->create(['slug' => 'crm']);
    $teammate = teammateWithPermissions($owner, ['account.connectors']);

    $this->actingAs($teammate)
        ->postJson(route('integrations.connectors.enable', ['slug' => 'crm']))
        ->assertOk();

    expect($server->fresh()->is_enabled)->toBeFalse();
});
