<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Models\ConnectorOAuthClient;
use Domain\Integration\Connector\Services\OAuth\McpOAuthDiscoverer;
use Domain\Integration\Connector\Services\OAuth\McpOAuthException;
use Domain\Integration\Connector\Services\OAuth\McpOAuthFlow;
use Domain\Integration\Connector\Services\OAuth\McpOAuthRegistrar;
use Domain\Integration\Connector\Services\OAuth\OAuthServerMetadata;
use Domain\Integration\Connector\Services\OAuth\PendingOAuthState;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Security properties of the MCP OAuth flow: PKCE is mandatory, pending
 * state is encrypted, state is bound to the starting user + session, and
 * concurrent token refreshes don't burn a rotating refresh token.
 */
function oauthFlowConnector(): ConnectorData
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
    ]);
}

/**
 * @param  array<string, mixed>  $metadata
 */
function oauthFlowWith(array $metadata, ?McpOAuthRegistrar $registrar = null): McpOAuthFlow
{
    $discoverer = Mockery::mock(McpOAuthDiscoverer::class);
    $discoverer->shouldReceive('discover')->andReturn(OAuthServerMetadata::fromArray('https://auth.example.test', $metadata));

    return new McpOAuthFlow($discoverer, $registrar ?? Mockery::mock(McpOAuthRegistrar::class), Mockery::mock(McpCatalog::class));
}

function staleOAuthServer(User $owner): AgentMcpServer
{
    return AgentMcpServer::create([
        'user_id' => $owner->id,
        'name' => 'Fixture',
        'slug' => 'fixture',
        'url' => 'https://mcp.example.test/rpc',
        'transport' => 'streamable_http',
        'auth_type' => 'bearer',
        'auth_value' => 'old-access',
        'is_enabled' => true,
        'oauth_authorization_server' => 'https://auth.example.test',
        'oauth_token_endpoint' => 'https://auth.example.test/token',
        'oauth_client_id' => 'client-123',
        'oauth_refresh_token' => 'old-refresh',
        'oauth_expires_at' => now()->subMinute(),
    ]);
}

it('refuses to start a flow when the authorization server does not advertise S256 PKCE', function (array $metadata): void {
    $registrar = Mockery::mock(McpOAuthRegistrar::class);
    $registrar->shouldNotReceive('clientFor');

    expect(fn (): string => oauthFlowWith($metadata, $registrar)->begin(User::factory()->create(), oauthFlowConnector(), 'binding'))
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

it('caches the pending state encrypted, including the client secret', function (): void {
    config(['cache.default' => 'array']);

    $client = new ConnectorOAuthClient;
    $client->client_id = 'client-123';
    $client->client_secret = 'very-secret-value';
    $client->token_endpoint = 'https://auth.example.test/token';
    $client->authorization_endpoint = 'https://auth.example.test/authorize';

    $registrar = Mockery::mock(McpOAuthRegistrar::class);
    $registrar->shouldReceive('clientFor')->andReturn($client);
    $registrar->shouldReceive('redirectUri')->andReturn('https://chatvia.test/connectors/oauth/callback');

    $url = oauthFlowWith([
        'authorization_endpoint' => 'https://auth.example.test/authorize',
        'token_endpoint' => 'https://auth.example.test/token',
        'code_challenge_methods_supported' => ['S256'],
    ], $registrar)->begin(User::factory()->create(), oauthFlowConnector(), 'binding');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $cached = Cache::get('oauth:mcp:state:'.$query['state']);

    expect($cached)->toBeString()
        ->not->toContain('very-secret-value')
        ->and(PendingOAuthState::unseal($cached)?->clientSecret)->toBe('very-secret-value');
});

it('reads a plain-array state cached before encryption and rejects garbage', function (): void {
    $legacy = [
        'user_id' => 5,
        'connector_slug' => 'notion',
        'auth_server_url' => 'https://auth.example.test',
        'token_endpoint' => 'https://auth.example.test/token',
        'client_id' => 'client-123',
        'code_verifier' => 'verifier',
        'mcp_url' => 'https://mcp.example.test/rpc',
    ];

    expect(PendingOAuthState::unseal($legacy)?->userId)->toBe(5)
        ->and(PendingOAuthState::unseal('not-encrypted'))->toBeNull()
        ->and(PendingOAuthState::unseal(null))->toBeNull();
});

it('rejects a callback from a different user without exchanging the code', function (): void {
    Http::fake();
    $starter = User::factory()->create();
    $other = User::factory()->create();

    $pending = new PendingOAuthState(
        userId: $starter->id,
        connectorSlug: 'notion',
        authServerUrl: 'https://auth.example.test',
        tokenEndpoint: 'https://auth.example.test/token',
        clientId: 'client-123',
        codeVerifier: 'verifier',
        mcpUrl: 'https://mcp.example.test/rpc',
        sessionBindingHash: PendingOAuthState::hashBinding('binding'),
    );
    Cache::put('oauth:mcp:state:abc', $pending->seal(), 600);

    expect(fn () => app(McpOAuthFlow::class)->complete($other, 'binding', 'abc', 'code'))
        ->toThrow(McpOAuthException::class, 'different account or browser');

    Http::assertNothingSent();
});

it('skips the refresh when another worker refreshed while it waited for the lock', function (): void {
    Http::fake();
    $server = staleOAuthServer(User::factory()->create());

    // Another worker already rotated the tokens in the database; our copy is stale.
    AgentMcpServer::query()->whereKey($server->id)->first()->forceFill([
        'auth_value' => 'new-access',
        'oauth_refresh_token' => 'new-refresh',
        'oauth_expires_at' => now()->addHour(),
    ])->save();

    app(McpOAuthFlow::class)->refreshIfStale($server);

    Http::assertNothingSent();
    expect($server->auth_value)->toBe('new-access')
        ->and($server->oauth_refresh_token)->toBe('new-refresh');
});

it('refreshes a stale token once under the lock and stores the rotated refresh token', function (): void {
    Http::fake([
        'auth.example.test/token' => Http::response([
            'access_token' => 'fresh-access',
            'refresh_token' => 'fresh-refresh',
            'expires_in' => 3600,
        ]),
    ]);
    $server = staleOAuthServer(User::factory()->create());

    app(McpOAuthFlow::class)->refreshIfStale($server);
    // A second caller holding the same (now fresh) row does nothing.
    app(McpOAuthFlow::class)->refreshIfStale($server->fresh());

    Http::assertSentCount(1);
    expect($server->fresh()->auth_value)->toBe('fresh-access')
        ->and($server->fresh()->oauth_refresh_token)->toBe('fresh-refresh');
});
