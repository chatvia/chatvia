<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\OAuth\BeginBringYourOwnClientOAuthAction;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Data\OAuth\BringYourOwnOAuthData;
use Domain\Integration\Connector\Data\OAuth\PendingOAuthState;
use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Illuminate\Support\Facades\Cache;

/**
 * A workspace-registered OAuth client drives PKCE straight off the supplied
 * client id + endpoints — no discovery, no dynamic registration.
 */
beforeEach(function (): void {
    config(['cache.default' => 'array']);
});

it('builds a PKCE authorize URL from the supplied client + endpoints', function (): void {
    $connector = ConnectorData::fromArray(config('connectors.salesforce'));
    $user = User::factory()->create();

    $byo = new BringYourOwnOAuthData(
        clientId: 'CONSUMER_KEY_123',
        clientSecret: 'consumer-secret',
        mcpUrl: 'https://api.salesforce.com/platform/mcp/v1/platform/sobject-all',
        authServerUrl: 'https://login.salesforce.com',
        authorizationEndpoint: 'https://login.salesforce.com/services/oauth2/authorize',
        tokenEndpoint: 'https://login.salesforce.com/services/oauth2/token',
    );

    $url = app(BeginBringYourOwnClientOAuthAction::class)->execute($user, $connector, $byo, 'session-binding');

    expect($url)->toStartWith('https://login.salesforce.com/services/oauth2/authorize?')
        ->and($url)->toContain('client_id=CONSUMER_KEY_123')
        ->and($url)->toContain('response_type=code')
        ->and($url)->toContain('code_challenge_method=S256')
        ->and($url)->toContain('code_challenge=')
        // mcp_api + refresh_token (http_build_query encodes the space as +).
        ->and($url)->toContain('scope=mcp_api+refresh_token')
        ->and($url)->toContain('redirect_uri=');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $pending = PendingOAuthState::unseal(Cache::get('oauth:mcp:state:'.$query['state']));

    // The client travels with the flow so the callback can store it on the row.
    expect($pending?->clientId)->toBe('CONSUMER_KEY_123')
        ->and($pending?->clientSecret)->toBe('consumer-secret')
        ->and($pending?->tokenEndpoint)->toBe('https://login.salesforce.com/services/oauth2/token');
});

it('exposes Salesforce as a per-workspace OAuth connector in the catalog', function (): void {
    $connector = ConnectorData::fromArray(config('connectors.salesforce'));

    expect($connector->authStrategy)->toBe(ConnectorAuthStrategy::Oauth)
        ->and($connector->requiresClientId)->toBeTrue()
        ->and($connector->requiresUrl)->toBeFalse()
        ->and($connector->oauthScopes)->toBe('mcp_api refresh_token')
        // No baked-in global client — it comes from the workspace.
        ->and($connector->oauthClientId)->toBeNull();
});
