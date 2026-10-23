<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\Account\ConnectTokenConnectorAction;
use Domain\Integration\Connector\Actions\Agent\EnableAgentCapabilityAction;
use Domain\Integration\Connector\Data\Account\ConnectTokenConnectorData;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Data\ProvisionedMcpServerData;
use Domain\Integration\Mcp\Models\AgentMcpServer;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->agent = makeTeamAgent($this->user, 'Support Bot');
    $this->charts = app(ConnectorCatalog::class)->capabilities()->get('charts');
});

it('attaches an already-provisioned capability without re-provisioning', function (): void {
    $server = AgentMcpServer::factory()->forOwner($this->user)->builtin('charts')->create();

    // No workspace-level connect should happen — the row already exists, so
    // the connect action (which does a remote tools/list) must never run.
    $this->mock(ConnectTokenConnectorAction::class)
        ->shouldReceive('execute')->never();

    $result = app(EnableAgentCapabilityAction::class)->execute($this->agent, $this->charts);

    expect($result->id)->toBe($server->id)
        ->and($this->agent->mcpServers()->whereKey($server->id)->exists())->toBeTrue()
        ->and(AgentMcpServer::query()->where('connector_slug', 'charts')->count())->toBe(1);
});

it('provisions the capability on first enable, then attaches it', function (): void {
    // No 'charts' row exists, so the action's own query finds nothing and it
    // connects the capability. The persisted row carries a null connector_slug
    // so that query can't match it; the stubbed connect action hands it back
    // as the freshly connected server (keeps the test offline).
    $provisioned = AgentMcpServer::factory()->forOwner($this->user)->builtin('charts')->create(['connector_slug' => null]);

    $this->mock(ConnectTokenConnectorAction::class)
        ->shouldReceive('execute')
        ->once()
        // Connected under the agent's owner — the workspace the server belongs to.
        ->withArgs(fn (ConnectTokenConnectorData $data): bool => $data->user->is($this->user) && $data->connector->slug === 'charts')
        ->andReturn(new ProvisionedMcpServerData(server: $provisioned, tools_count: 12));

    app(EnableAgentCapabilityAction::class)->execute($this->agent, $this->charts);

    expect($this->agent->mcpServers()->whereKey($provisioned->id)->exists())->toBeTrue();
});
