<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\OAuth\BeginConnectorOAuthAction;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Data\OAuth\OAuthServerMetadataData;
use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Domain\Integration\Connector\Exceptions\ConnectorMisconfiguredException;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Connector\Services\OAuth\McpOAuthDiscoverer;
use Domain\Integration\Connector\Services\OAuth\McpOAuthRegistrar;
use Illuminate\Support\Uri;
use Tests\Support\Integration\Connector\PendingConnectorOAuth;

/**
 * Starting a catalog connector's OAuth flow: PKCE S256 is mandatory, the
 * pending state is cached encrypted and bound to the user + browser session,
 * the token is bound to the MCP server (RFC 8707 `resource`), and a connector
 * wired for a pre-registered OAuth app whose client id the operator never set
 * is a reportable misconfiguration rather than a user error.
 */

/**
 * @param  array<string, mixed>  $overrides
 */
function bcoConnector(array $overrides = []): ConnectorData
{
    return new ConnectorData(...[
        'slug' => 'fixture',
        'name' => 'Fixture',
        'description' => 'd',
        'category' => 'Test',
        'icon' => 'fixture',
        'brandColor' => '000000',
        'authStrategy' => ConnectorAuthStrategy::Oauth,
        'mcpUrl' => 'https://mcp.example.test/rpc',
        'priority' => 999,
        ...$overrides,
    ]);
}

it('returns the authorize URL with PKCE and caches the encrypted, bound state', function (): void {
    PendingConnectorOAuth::stubAuthorizationServer(clientSecret: 'very-secret-value');
    $user = User::factory()->create();

    $url = app(BeginConnectorOAuthAction::class)->execute($user, bcoConnector(), 'session-binding');

    expect($url)->toStartWith('https://auth.example.test/authorize?');

    $query = Uri::of($url)->query();
    expect($query->get('response_type'))->toBe('code')
        ->and($query->get('client_id'))->toBe('oauth-client-xyz')
        ->and($query->get('code_challenge_method'))->toBe('S256')
        ->and($query->get('redirect_uri'))->toBe('https://chatvia.test/connectors/oauth/callback')
        ->and($query->get('state'))->not->toBeEmpty()
        ->and($query->get('code_challenge'))->not->toBeEmpty()
        ->and($query->get('resource'))->toBe('https://mcp.example.test/rpc');

    $cached = PendingConnectorOAuth::sealedFor($url);
    $pending = PendingConnectorOAuth::fromAuthorizeUrl($url);

    expect($cached)->toBeString()
        ->not->toContain('very-secret-value')
        ->not->toContain('oauth-client-xyz')
        ->and($pending?->userId)->toBe($user->id)
        ->and($pending?->clientSecret)->toBe('very-secret-value')
        ->and($pending?->connectorSlug)->toBe('fixture')
        ->and($pending?->matchesSession('session-binding'))->toBeTrue()
        ->and($pending?->matchesSession('another-browser'))->toBeFalse();
});

it('refuses to start when the authorization server does not advertise S256 PKCE', function (array $metadata): void {
    test()->mock(McpOAuthDiscoverer::class)->shouldReceive('discover')
        ->andReturn(OAuthServerMetadataData::fromArray('https://auth.example.test', $metadata));
    test()->mock(McpOAuthRegistrar::class)->shouldNotReceive('clientFor');

    expect(fn (): string => app(BeginConnectorOAuthAction::class)->execute(User::factory()->create(), bcoConnector(), 'binding'))
        ->toThrow(McpOAuthException::class, 'PKCE');
})->with([
    'methods missing' => [[
        'authorization_endpoint' => 'https://auth.example.test/authorize',
        'token_endpoint' => 'https://auth.example.test/token',
    ]],
    'plain only' => [[
        'authorization_endpoint' => 'https://auth.example.test/authorize',
        'token_endpoint' => 'https://auth.example.test/token',
        'code_challenge_methods_supported' => ['plain'],
    ]],
]);

it('raises a misconfiguration when the pre-registered OAuth app credentials are missing', function (): void {
    test()->mock(McpOAuthDiscoverer::class)->shouldNotReceive('discover');

    $connector = bcoConnector([
        'oauthAuthorizationEndpoint' => 'https://auth.example.test/authorize',
        'oauthTokenEndpoint' => 'https://auth.example.test/token',
        'oauthClientId' => null,
    ]);

    expect(fn (): string => app(BeginConnectorOAuthAction::class)->execute(User::factory()->create(), $connector, 'binding'))
        ->toThrow(ConnectorMisconfiguredException::class);
});
