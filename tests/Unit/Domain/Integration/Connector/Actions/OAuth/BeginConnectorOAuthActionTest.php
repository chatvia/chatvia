<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\OAuth\BeginConnectorOAuthAction;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Data\OAuth\OAuthServerMetadata;
use Domain\Integration\Connector\Data\OAuth\PendingOAuthState;
use Domain\Integration\Connector\Exceptions\ConnectorMisconfiguredException;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Connector\Models\ConnectorOAuthClient;
use Domain\Integration\Connector\Services\OAuth\McpOAuthDiscoverer;
use Domain\Integration\Connector\Services\OAuth\McpOAuthRegistrar;
use Illuminate\Support\Facades\Cache;

/**
 * Starting a catalog connector's OAuth flow: PKCE S256 is mandatory, the
 * pending state is cached encrypted and bound to the user + browser session,
 * and a connector whose operator credentials are missing is a reportable
 * misconfiguration rather than a user error.
 */
beforeEach(function (): void {
    config(['cache.default' => 'array']);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function bcoConnector(array $overrides = []): ConnectorData
{
    return ConnectorData::fromArray([
        'slug' => 'fixture',
        'name' => 'Fixture',
        'description' => 'd',
        'category' => 'Test',
        'icon' => 'fixture',
        'brand_color' => '000000',
        'auth_strategy' => 'oauth',
        'mcp_url' => 'https://mcp.example.test/rpc',
        'priority' => 999,
        ...$overrides,
    ]);
}

/**
 * Stub discovery (with the given metadata) and the registrar (returning a
 * known client), so the test never reaches the network.
 *
 * @param  array<string, mixed>  $metadata
 */
function bcoStubDiscovery(array $metadata, ?string $clientSecret = null): ConnectorOAuthClient
{
    test()->mock(McpOAuthDiscoverer::class)->shouldReceive('discover')
        ->andReturn(OAuthServerMetadata::fromArray('https://auth.example.test', $metadata));

    $client = new ConnectorOAuthClient()->forceFill([
        'auth_server_url' => 'https://auth.example.test',
        'client_id' => 'oauth-client-xyz',
        'client_secret' => $clientSecret,
        'token_endpoint' => 'https://auth.example.test/token',
        'authorization_endpoint' => 'https://auth.example.test/authorize',
    ]);

    $registrar = test()->mock(McpOAuthRegistrar::class);
    $registrar->shouldReceive('clientFor')->andReturn($client);
    $registrar->shouldReceive('redirectUri')->andReturn('https://chatvia.test/connectors/oauth/callback');

    return $client;
}

function bcoPkceMetadata(): array
{
    return [
        'authorization_endpoint' => 'https://auth.example.test/authorize',
        'token_endpoint' => 'https://auth.example.test/token',
        'code_challenge_methods_supported' => ['S256'],
    ];
}

it('returns the authorize URL with PKCE and caches the encrypted, bound state', function (): void {
    bcoStubDiscovery(bcoPkceMetadata(), clientSecret: 'very-secret-value');
    $user = User::factory()->create();

    $url = app(BeginConnectorOAuthAction::class)->execute($user, bcoConnector(), 'session-binding');

    expect($url)->toStartWith('https://auth.example.test/authorize?');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    expect($query['response_type'])->toBe('code')
        ->and($query['client_id'])->toBe('oauth-client-xyz')
        ->and($query['code_challenge_method'])->toBe('S256')
        ->and($query['redirect_uri'])->toBe('https://chatvia.test/connectors/oauth/callback')
        ->and($query['state'])->not->toBeEmpty()
        ->and($query['code_challenge'])->not->toBeEmpty();

    $cached = Cache::get('oauth:mcp:state:'.$query['state']);
    $pending = PendingOAuthState::unseal($cached);

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
        ->andReturn(OAuthServerMetadata::fromArray('https://auth.example.test', $metadata));
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

    // Wired for a pre-registered OAuth app (authorization endpoint set) but the
    // operator never set the client id: reportable, not a user-facing error.
    $connector = bcoConnector([
        'oauth_authorization_endpoint' => 'https://auth.example.test/authorize',
        'oauth_token_endpoint' => 'https://auth.example.test/token',
        'oauth_client_id' => null,
    ]);

    expect(fn (): string => app(BeginConnectorOAuthAction::class)->execute(User::factory()->create(), $connector, 'binding'))
        ->toThrow(ConnectorMisconfiguredException::class);
});
