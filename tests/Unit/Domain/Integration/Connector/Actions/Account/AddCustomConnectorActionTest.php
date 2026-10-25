<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\Account\AddCustomConnectorAction;
use Domain\Integration\Connector\Data\Custom\AddCustomConnectorData;
use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Domain\Integration\Mcp\Actions\Catalog\SyncMcpServerCatalogAction;
use Domain\Integration\Mcp\Enums\McpAuthType;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Exceptions\McpException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Database\Eloquent\Collection;

/**
 * Adding a custom MCP server by URL: owned by the workspace, a slug
 * that is free in the workspace, and nothing left behind when the first
 * catalog sync fails.
 */
function accData(User $user, ConnectorAuthStrategy $strategy, ?string $token = null, string $name = 'My CRM'): AddCustomConnectorData
{
    return new AddCustomConnectorData(
        user: $user,
        name: $name,
        url: 'https://crm.example.test/mcp',
        auth_strategy: $strategy,
        token: $token,
    );
}

it('adds a token-auth custom server under the workspace with a workspace-unique slug', function (): void {
    $owner = User::factory()->create();
    $teammate = User::factory()->employee($owner)->create();
    AgentMcpServer::factory()->forOwner($owner)->create(['name' => 'My CRM', 'slug' => 'my_crm']);
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')->andReturn(new Collection);

    $result = app(AddCustomConnectorAction::class)->execute(accData($teammate, ConnectorAuthStrategy::Token, 'tok'));

    expect($result->server->workspace_id)->toBe($owner->workspace()->id)
        ->and($result->server->category)->toBe(McpServerCategory::Custom)
        ->and($result->server->connector_slug)->toBeNull()
        ->and($result->server->slug)->toBe('my_crm_2')
        ->and($result->server->auth_type)->toBe(McpAuthType::Bearer)
        ->and($result->server->auth_value)->toBe('tok')
        ->and($result->tools_count)->toBe(0);
});

it('adds a no-auth custom server without credentials', function (): void {
    $owner = User::factory()->create();
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')->andReturn(new Collection);

    $result = app(AddCustomConnectorAction::class)->execute(accData($owner, ConnectorAuthStrategy::None, 'ignored'));

    expect($result->server->auth_type)->toBe(McpAuthType::None)
        ->and($result->server->auth_value)->toBeNull();
});

it('removes the server again when its first catalog sync fails', function (): void {
    $owner = User::factory()->create();
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')->andThrow(new McpException('unreachable'));

    expect(fn () => app(AddCustomConnectorAction::class)->execute(accData($owner, ConnectorAuthStrategy::None)))
        ->toThrow(McpException::class);

    expect($owner->workspace()->mcpServers()->count())->toBe(0);
});

it('never gives a custom server the slug of a builtin or catalog connector', function (string $name, string $slug): void {
    $owner = User::factory()->create();
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')->andReturn(new Collection);

    $result = app(AddCustomConnectorAction::class)->execute(accData($owner, ConnectorAuthStrategy::None, name: $name));

    expect($result->server->slug)->toBe($slug);
})->with([
    'the builtin forms' => ['Forms', 'forms_2'],
    'the builtin charts' => ['Charts', 'charts_2'],
    'a catalog connector' => ['Notion', 'notion_2'],
]);
