<?php

use App\Identity\Workspace\Models\User;
use Domain\Form\Actions\EnableFormsCapabilityAction;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Adding a form turns the agent's Forms capability on: the workspace's forms
 * server is attached to the agent once, however often it is asked.
 */
it('turns Forms on for the agent, once', function (): void {
    $user = User::factory()->create();
    $agent = makeTeamAgent($user, 'Support Bot');
    AgentMcpServer::factory()->forOwner($user)->formsCapability()->create();

    app(EnableFormsCapabilityAction::class)->execute($agent);
    app(EnableFormsCapabilityAction::class)->execute($agent);

    expect($agent->isFormsEnabled())->toBeTrue()
        ->and($agent->mcpServers()->count())->toBe(1);
});
