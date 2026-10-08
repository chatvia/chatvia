<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Data\OAuth\BringYourOwnOAuthData;
use Domain\Integration\Connector\Services\OAuth\McpOAuthFlow;

/**
 * beginWithClient drives PKCE straight off a workspace-supplied client_id +
 * endpoints — no discovery, no DCR — and returns the upstream authorize URL.
 */
beforeEach(function (): void {
    // PendingOAuthState is stashed in the default cache store; use array so the
    // test never needs Redis.
    config(['cache.default' => 'array']);
});

it('builds a PKCE authorize URL from the supplied client + endpoints', function (): void {
    $connector = ConnectorData::fromArray(config('connectors.salesforce'));
    $user = User::factory()->create();

    $byo = new BringYourOwnOAuthData(
        clientId: 'CONSUMER_KEY_123',
        clientSecret: null,
        mcpUrl: 'https://api.salesforce.com/platform/mcp/v1/platform/sobject-all',
        authServerUrl: 'https://login.salesforce.com',
        authorizationEndpoint: 'https://login.salesforce.com/services/oauth2/authorize',
        tokenEndpoint: 'https://login.salesforce.com/services/oauth2/token',
    );

    $url = app(McpOAuthFlow::class)->beginWithClient($user, $connector, $byo, 'session-binding');

    expect($url)->toStartWith('https://login.salesforce.com/services/oauth2/authorize?')
        ->and($url)->toContain('client_id=CONSUMER_KEY_123')
        ->and($url)->toContain('response_type=code')
        ->and($url)->toContain('code_challenge_method=S256')
        ->and($url)->toContain('code_challenge=')
        ->and($url)->toContain('state=')
        // mcp_api + refresh_token (http_build_query encodes the space as +).
        ->and($url)->toContain('scope=mcp_api+refresh_token')
        ->and($url)->toContain('redirect_uri=');
});

it('exposes Salesforce as a per-workspace OAuth connector in the catalog', function (): void {
    $connector = ConnectorData::fromArray(config('connectors.salesforce'));

    expect($connector->authStrategy)->toBe('oauth')
        ->and($connector->requiresClientId)->toBeTrue()
        ->and($connector->requiresUrl)->toBeFalse()
        ->and($connector->oauthScopes)->toBe('mcp_api refresh_token')
        // No baked-in global client — it comes from the workspace.
        ->and($connector->oauthClientId)->toBeNull();
});
