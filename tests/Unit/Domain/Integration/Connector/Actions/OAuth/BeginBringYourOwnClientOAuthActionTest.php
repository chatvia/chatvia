<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\OAuth\BeginBringYourOwnClientOAuthAction;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Data\OAuth\BringYourOwnOAuthData;
use Illuminate\Support\Uri;
use Tests\Support\Integration\Connector\PendingConnectorOAuth;

/**
 * A workspace-registered OAuth client drives PKCE straight off the supplied
 * client id + endpoints — no discovery, no dynamic registration.
 */
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
        // mcp_api + refresh_token (Uri encodes the space as %20, RFC 3986).
        ->and($url)->toContain('scope=mcp_api%20refresh_token')
        ->and($url)->toContain('redirect_uri=');

    $pending = PendingConnectorOAuth::fromAuthorizeUrl($url);

    // The client travels with the flow so the callback can store it on the row.
    expect($pending?->clientId)->toBe('CONSUMER_KEY_123')
        ->and($pending?->clientSecret)->toBe('consumer-secret')
        ->and($pending?->tokenEndpoint)->toBe('https://login.salesforce.com/services/oauth2/token');
});
