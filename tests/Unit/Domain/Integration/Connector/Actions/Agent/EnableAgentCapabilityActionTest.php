<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\Agent\EnableAgentCapabilityAction;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Actions\Catalog\SyncMcpServerCatalogAction;
use Domain\Integration\Mcp\Enums\McpAuthType;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Database\Eloquent\Collection;

/**
 * Turning a first-party capability (Interactive Charts) on for an agent: the
 * first enable in the workspace connects the workspace's server for it, under
 * the workspace; later enables reuse that server and only attach it.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->agent = makeTeamAgent($this->user, 'Support Bot');
    $this->charts = app(ConnectorCatalog::class)->capabilities()->get('charts');
});

it('attaches an already-connected capability without connecting it again', function (): void {
    $server = AgentMcpServer::factory()->forOwner($this->user)->builtin('charts')->create();
    // Connecting would fetch the tool catalog: it must not happen.
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')->never();

    $result = app(EnableAgentCapabilityAction::class)->execute($this->agent, $this->charts);

    expect($result->id)->toBe($server->id)
        ->and($this->agent->mcpServers()->whereKey($server->id)->exists())->toBeTrue()
        ->and(AgentMcpServer::query()->where('connector_slug', 'charts')->count())->toBe(1);
});

it('connects the capability for the workspace on its first enable, then attaches it', function (): void {
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')->once()->andReturn(new Collection);

    $result = app(EnableAgentCapabilityAction::class)->execute($this->agent, $this->charts);

    $server = AgentMcpServer::query()->where('connector_slug', 'charts')->sole();
    expect($result->id)->toBe($server->id)
        ->and($server->workspace_id)->toBe($this->user->workspace()->id)
        ->and($server->auth_type)->toBe(McpAuthType::None)
        ->and($this->agent->mcpServers()->whereKey($server->id)->exists())->toBeTrue();
});
