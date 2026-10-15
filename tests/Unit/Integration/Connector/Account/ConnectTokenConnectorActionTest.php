<?php

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

it('connects a no-auth capability without credentials', function (): void {
    $owner = User::factory()->create();
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')->andReturn(new Collection);

    $result = app(ConnectTokenConnectorAction::class)->execute(new ConnectTokenConnectorData(
        user: $owner,
        connector: ctcConnector('charts'),
        token: 'ignored',
    ));

    expect($result->server->auth_type)->toBe(McpAuthType::None)
        ->and($result->server->auth_value)->toBeNull();
});

it('removes a first connect whose catalog sync fails, with a message the user can act on', function (): void {
    $owner = User::factory()->create();
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')
        ->andThrow(new McpException('MCP server returned HTTP 401: nope', 401));

    expect(fn () => app(ConnectTokenConnectorAction::class)->execute(new ConnectTokenConnectorData(
        user: $owner,
        connector: ctcConnector('weather'),
        token: 'bad-token',
    )))->toThrow(McpException::class, 'The server rejected the token (401 Unauthorized).');

    expect($owner->workspace()->mcpServers()->count())->toBe(0);
});

it('restores the previous token when a token swap fails', function (): void {
    $owner = User::factory()->create();
    $sync = $this->mock(SyncMcpServerCatalogAction::class);

    $sync->shouldReceive('execute')->once()->andReturn(new Collection);
    app(ConnectTokenConnectorAction::class)->execute(new ConnectTokenConnectorData($owner, ctcConnector('weather'), 'good-token'));

    $sync->shouldReceive('execute')->once()->andThrow(new McpException('Could not reach MCP server: timed out'));

    expect(fn () => app(ConnectTokenConnectorAction::class)->execute(new ConnectTokenConnectorData($owner, ctcConnector('weather'), 'new-token')))
        ->toThrow(McpException::class, 'Could not reach the MCP server.');

    $server = $owner->workspace()->mcpServers()->sole();
    expect($server->auth_value)->toBe('good-token');
});

it('explains a failed first connect in words the user can act on', function (string $raw, string $shown): void {
    $owner = User::factory()->create();
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')->andThrow(new McpException($raw));

    expect(fn () => app(ConnectTokenConnectorAction::class)->execute(new ConnectTokenConnectorData($owner, ctcConnector('weather'), 'tok')))
        ->toThrow(McpException::class, $shown);
})->with([
    'forbidden' => ['MCP server returned HTTP 403: forbidden', 'The token is valid but does not have permission to call this MCP server (403).'],
    'not found' => ['MCP server returned HTTP 404: not found', 'The connector endpoint returned 404. The service may have moved — please report this.'],
    'anything else' => ['tools/list returned malformed JSON', 'tools/list returned malformed JSON'],
]);

it('connects to the workspace\'s own URL when one is given, else the connector\'s', function (): void {
    $owner = User::factory()->create();
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')->andReturn(new Collection);
    $connector = ctcConnector('weather');

    $default = app(ConnectTokenConnectorAction::class)->execute(new ConnectTokenConnectorData($owner, $connector, 'tok'));
    expect($default->server->url)->toBe($connector->mcpUrl);

    $custom = app(ConnectTokenConnectorAction::class)->execute(new ConnectTokenConnectorData($owner, $connector, 'tok', url: 'https://mcp.self-hosted.example.test/mcp'));
    expect($custom->server->url)->toBe('https://mcp.self-hosted.example.test/mcp')
        ->and($custom->server->id)->toBe($default->server->id);
});
