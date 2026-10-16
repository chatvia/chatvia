<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\Agent\DisableAgentCapabilityAction;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\AgentMcpServer;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->agent = makeTeamAgent($this->user, 'Support Bot');
    AgentMcpServer::factory()->builtin('forms')->attachedTo($this->agent)->create();
});

it('detaches the capability from the agent', function (): void {
    expect($this->agent->isFormsEnabled())->toBeTrue();

    $forms = app(ConnectorCatalog::class)->capabilities()->get('forms');
    app(DisableAgentCapabilityAction::class)->execute($this->agent, $forms);

    expect($this->agent->mcpServers()->where('connector_slug', 'forms')->exists())->toBeFalse();
});

it('leaves the account-level server rows intact (detaches the pivot only)', function (): void {
    $forms = app(ConnectorCatalog::class)->capabilities()->get('forms');
    app(DisableAgentCapabilityAction::class)->execute($this->agent, $forms);

    expect(AgentMcpServer::query()->where('connector_slug', 'forms')->where('workspace_id', $this->user->workspace()->id)->exists())->toBeTrue();
});
