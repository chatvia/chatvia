<?php

use Domain\Integration\Connector\Actions\Agent\RefreshAgentConnectorCatalogAction;
use Domain\Integration\Connector\Exceptions\ConnectorRefreshFailedException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpCatalog;
use Domain\Integration\Mcp\Services\McpException;

/**
 * A failed catalog refresh becomes a typed exception: MCP errors keep their
 * message, anything else gets a generic one, and the cause is chained.
 */
it('wraps failures in ConnectorRefreshFailedException and keeps the cause', function (Throwable $cause, string $expected): void {
    $catalog = Mockery::mock(McpCatalog::class);
    $catalog->shouldReceive('refresh')->andThrow($cause);

    try {
        (new RefreshAgentConnectorCatalogAction($catalog))->execute(new AgentMcpServer);
        $this->fail('Expected ConnectorRefreshFailedException');
    } catch (ConnectorRefreshFailedException $e) {
        expect($e->getMessage())->toBe($expected)
            ->and($e->getPrevious())->toBe($cause);
    }
})->with([
    'MCP error keeps its message' => [new McpException('MCP server returned HTTP 401: unauthorized', 401), 'MCP server returned HTTP 401: unauthorized'],
    'other errors become generic' => [new RuntimeException('SQLSTATE secret detail'), 'Could not refresh the connector.'],
]);
