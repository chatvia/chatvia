<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\Account\ConnectTokenConnectorAction;
use Domain\Integration\Connector\Data\Account\ConnectTokenConnectorData;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Actions\Catalog\SyncMcpServerCatalogAction;
use Domain\Integration\Mcp\Enums\McpAuthType;
use Domain\Integration\Mcp\Exceptions\McpException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Illuminate\Database\Eloquent\Collection;

/**
 * Connecting a token / no-auth catalog connector: one server per connector
 * per workspace, owned by the workspace, and a failed catalog sync
 * leaves the workspace exactly as it was.
 */
function ctcConnector(string $slug): ConnectorData
{
    $connector = app(ConnectorCatalog::class)->findTokenConnector($slug);
    expect($connector)->not->toBeNull();

    return $connector;
}

it('connects a token connector under the workspace and reports its tools', function (): void {
    $owner = User::factory()->create();
    $teammate = User::factory()->employee($owner)->create();
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')
        ->andReturn(new Collection([new McpTool, new McpTool]));

    $result = app(ConnectTokenConnectorAction::class)->execute(new ConnectTokenConnectorData(
        user: $teammate,
        connector: ctcConnector('weather'),
        token: 'tok-123',
    ));

    expect($result->tools_count)->toBe(2)
        ->and($result->server->workspace_id)->toBe($owner->workspace()->id)
        ->and($result->server->connector_slug)->toBe('weather')
        ->and($result->server->auth_type)->toBe(McpAuthType::Bearer)
        ->and($result->server->auth_value)->toBe('tok-123')
        ->and($result->server->timeout_ms)->toBe(AgentMcpServer::DEFAULT_TIMEOUT_MS);
});

it('leaves the workspace as it was when a catalog sync fails: a first connect is removed, a token swap keeps the old token', function (): void {
    $owner = User::factory()->create();
    $sync = $this->mock(SyncMcpServerCatalogAction::class);
    $connect = fn (string $token) => app(ConnectTokenConnectorAction::class)->execute(new ConnectTokenConnectorData($owner, ctcConnector('weather'), $token));

    $sync->shouldReceive('execute')->once()->andThrow(new McpException('MCP server returned HTTP 401: nope', 401));
    expect(fn () => $connect('bad-token'))->toThrow(McpException::class);
    expect($owner->workspace()->mcpServers()->count())->toBe(0);

    $sync->shouldReceive('execute')->once()->andReturn(new Collection);
    $connect('good-token');
    $sync->shouldReceive('execute')->once()->andThrow(new McpException('Could not reach MCP server: timed out'));
    expect(fn () => $connect('new-token'))->toThrow(McpException::class, 'Could not reach the MCP server.');

    expect($owner->workspace()->mcpServers()->sole()->auth_value)->toBe('good-token');
});
