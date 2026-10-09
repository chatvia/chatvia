<?php

use Domain\Integration\Connector\Actions\Catalog\RefreshConnectorCatalogAction;
use Domain\Integration\Connector\Exceptions\ConnectorRefreshFailedException;
use Domain\Integration\Mcp\Actions\Catalog\SyncMcpServerCatalogAction;
use Domain\Integration\Mcp\Exceptions\McpException;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * A failed catalog refresh becomes a typed exception: MCP errors keep their
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
