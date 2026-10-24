<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\Agent\DisableAgentCapabilityAction;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Turning a capability off for one agent only detaches it: the workspace's
 * server stays for its other agents.
 */
it('detaches the capability from the agent and keeps the workspace\'s server', function (): void {
    $user = User::factory()->create();
    $agent = makeTeamAgent($user, 'Support Bot');
    $server = AgentMcpServer::factory()->builtin('forms')->attachedTo($agent)->create();

    app(DisableAgentCapabilityAction::class)->execute($agent, app(ConnectorCatalog::class)->capabilities()->get('forms'));

    expect($agent->mcpServers()->where('connector_slug', 'forms')->exists())->toBeFalse()
        ->and($server->fresh()?->workspace_id)->toBe($user->workspace()->id);
});
