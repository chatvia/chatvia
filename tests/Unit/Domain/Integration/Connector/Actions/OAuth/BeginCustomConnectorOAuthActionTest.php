<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\OAuth\BeginCustomConnectorOAuthAction;
use Domain\Integration\Connector\Data\Custom\AddCustomConnectorData;
use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Tests\Support\Integration\Connector\PendingConnectorOAuth;

/**
 * A custom server added with OAuth carries its name and a slug that is free
 * in the WORKSPACE (the callback saves it under the owner) through the
 * pending state.
 */
it('carries the custom name and a workspace-unique slug in the pending state', function (): void {
    PendingConnectorOAuth::stubAuthorizationServer();

    $owner = User::factory()->create();
    $teammate = User::factory()->employee($owner)->create();
    // The owner already has a server with the name's slug.
    AgentMcpServer::factory()->forOwner($owner)->create(['name' => 'My CRM', 'slug' => 'my_crm']);

    $url = app(BeginCustomConnectorOAuthAction::class)->execute(new AddCustomConnectorData(
        user: $teammate,
        name: 'My CRM',
        url: 'https://crm.example.test/mcp',
        auth_strategy: ConnectorAuthStrategy::Oauth,
    ), 'binding');

    $pending = PendingConnectorOAuth::fromAuthorizeUrl($url);

    expect($pending?->connectorSlug)->toBeNull()
        ->and($pending?->customName)->toBe('My CRM')
        ->and($pending?->customSlug)->toBe('my_crm_2')
        ->and($pending?->mcpUrl)->toBe('https://crm.example.test/mcp')
        ->and($pending?->userId)->toBe($teammate->id);
});
