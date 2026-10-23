<?php

use Domain\Integration\Connector\Actions\Catalog\RefreshConnectorCatalogAction;
use Domain\Integration\Connector\Exceptions\ConnectorRefreshFailedException;
use Domain\Integration\Mcp\Actions\Catalog\SyncMcpServerCatalogAction;
use Domain\Integration\Mcp\Exceptions\McpException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Illuminate\Database\Eloquent\Collection;

/**
 * Refreshing a connector re-syncs its tool catalog and returns the server with
 * its tool count. A failure becomes a typed exception: MCP errors keep their
 * message, anything else gets a generic one, and the cause is chained.
 */
it('wraps failures in ConnectorRefreshFailedException and keeps the cause', function (Throwable $cause, string $expected): void {
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')->andThrow($cause);

    try {
        app(RefreshConnectorCatalogAction::class)->execute(new AgentMcpServer);
        $this->fail('Expected ConnectorRefreshFailedException');
    } catch (ConnectorRefreshFailedException $e) {
        expect($e->getMessage())->toBe($expected)
            ->and($e->getPrevious())->toBe($cause);
    }
})->with([
    'MCP error keeps its message' => [new McpException('MCP server returned HTTP 401: unauthorized', 401), 'MCP server returned HTTP 401: unauthorized'],
    'other errors become generic' => [new RuntimeException('SQLSTATE secret detail'), 'Could not refresh the connector.'],
]);

it('returns the refreshed server and its tool count', function (): void {
    $server = AgentMcpServer::factory()->create();
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')
        ->once()
        ->andReturnUsing(function (AgentMcpServer $synced): Collection {
            // The sync writes the row, not the caller's copy.
            AgentMcpServer::query()->whereKey($synced->getKey())->update(['catalog_cached_at' => now()]);

            return new Collection([new McpTool, new McpTool, new McpTool]);
        });

    $result = app(RefreshConnectorCatalogAction::class)->execute($server);

    expect($result->tools_count)->toBe(3)
        ->and($result->data->external_id)->toBe($server->external_id)
        ->and($result->data->catalog_cached_at)->not->toBeNull();
});
