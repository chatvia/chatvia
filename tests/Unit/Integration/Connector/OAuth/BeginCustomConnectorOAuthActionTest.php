<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\OAuth\BeginCustomConnectorOAuthAction;
use Domain\Integration\Connector\Data\Custom\AddCustomConnectorData;
use Domain\Integration\Connector\Data\OAuth\OAuthServerMetadata;
use Domain\Integration\Connector\Data\OAuth\PendingOAuthState;
use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Domain\Integration\Connector\Models\ConnectorOAuthClient;
use Domain\Integration\Connector\Services\OAuth\McpOAuthDiscoverer;
use Domain\Integration\Connector\Services\OAuth\McpOAuthRegistrar;
use Illuminate\Support\Facades\Cache;

/**
 * A custom server added with OAuth carries its name and a slug that is free
 * in the WORKSPACE (the callback saves it under the owner) through the
 * pending state.
 */
it('carries the custom name and a workspace-unique slug in the pending state', function (): void {
    config(['cache.default' => 'array']);

    $this->mock(McpOAuthDiscoverer::class)->shouldReceive('discover')
        ->andReturn(OAuthServerMetadata::fromArray('https://auth.example.test', [
            'authorization_endpoint' => 'https://auth.example.test/authorize',
            'token_endpoint' => 'https://auth.example.test/token',
            'code_challenge_methods_supported' => ['S256'],
        ]));
    $registrar = $this->mock(McpOAuthRegistrar::class);
    $registrar->shouldReceive('clientFor')->andReturn(new ConnectorOAuthClient()->forceFill([
        'auth_server_url' => 'https://auth.example.test',
        'client_id' => 'client-123',
        'token_endpoint' => 'https://auth.example.test/token',
        'authorization_endpoint' => 'https://auth.example.test/authorize',
    ]));
    $registrar->shouldReceive('redirectUri')->andReturn('https://chatvia.test/connectors/oauth/callback');

    $owner = User::factory()->create();
    $teammate = User::factory()->employee($owner)->create();
    // The owner already has a server with the name's slug.
    $owner->workspace()->mcpServers()->create(['name' => 'My CRM', 'slug' => 'my_crm', 'url' => 'https://crm.example.test/mcp', 'auth_type' => 'none']);

    $url = app(BeginCustomConnectorOAuthAction::class)->execute(new AddCustomConnectorData(
        user: $teammate,
        name: 'My CRM',
        url: 'https://crm.example.test/mcp',
        auth_strategy: ConnectorAuthStrategy::Oauth,
    ), 'binding');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $pending = PendingOAuthState::unseal(Cache::get('oauth:mcp:state:'.$query['state']));

    expect($pending?->connectorSlug)->toBeNull()
        ->and($pending?->customName)->toBe('My CRM')
        ->and($pending?->customSlug)->toBe('my_crm_2')
        ->and($pending?->mcpUrl)->toBe('https://crm.example.test/mcp')
        ->and($pending?->userId)->toBe($teammate->id);
});
