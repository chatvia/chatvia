<?php

use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Connector\Models\ConnectorOAuthClient;
use Domain\Integration\Connector\Services\OAuth\OAuthTokenRefresher;
use Domain\Integration\Mcp\Enums\McpAuthType;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

/**
 * Background token refresh: rotating refresh tokens must not be burned by two
 * workers at once, so a refresh runs under a per-server lock and re-checks
 * staleness after taking it. It never posts the refresh token to an unsafe
 * endpoint (private ranges are relaxed in tests, so a scheme SafeUrl always
 * refuses stands in), and it authenticates with the client the server was
 * connected with: the connector's own OAuth app, the row's client, or the
 * client registered with the authorization server.
 */

/**
 * An OAuth-connected server whose access token expired a minute ago.
 *
 * @param  array<string, mixed>  $overrides
 */
function otrStaleServer(array $overrides = []): AgentMcpServer
{
    return AgentMcpServer::factory()->create([
        'name' => 'Fixture',
        'slug' => 'fixture',
        'url' => 'https://mcp.example.test/rpc',
        'auth_type' => McpAuthType::Bearer,
        'auth_value' => 'old-access',
        'oauth_authorization_server' => 'https://auth.example.test',
        'oauth_token_endpoint' => 'https://auth.example.test/token',
        'oauth_client_id' => 'client-123',
        'oauth_refresh_token' => 'old-refresh',
        'oauth_expires_at' => now()->subMinute(),
        ...$overrides,
    ]);
}

it('swaps in the rotated tokens once under the lock, so a worker holding the stale row sends nothing', function (): void {
    Http::fake([
        'auth.example.test/token' => Http::response([
            'access_token' => 'fresh-access',
            'refresh_token' => 'fresh-refresh',
            'expires_in' => 3600,
            'scope' => 'read',
        ]),
    ]);
    $server = otrStaleServer();
    $staleCopy = AgentMcpServer::query()->findOrFail($server->id);

    app(OAuthTokenRefresher::class)->refreshIfStale($server);
    app(OAuthTokenRefresher::class)->refreshIfStale($staleCopy);
    app(OAuthTokenRefresher::class)->refreshIfStale($server->fresh());

    expect($server->fresh()->auth_value)->toBe('fresh-access')
        ->and($server->fresh()->oauth_refresh_token)->toBe('fresh-refresh')
        ->and($server->fresh()->oauth_scopes)->toBe('read')
        ->and($server->fresh()->accessTokenIsStale())->toBeFalse()
        ->and($staleCopy->auth_value)->toBe('fresh-access')
        ->and($staleCopy->oauth_refresh_token)->toBe('fresh-refresh');
    Http::assertSentCount(1);
    Http::assertSent(fn (ClientRequest $request): bool => $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === 'old-refresh'
        && $request['client_id'] === 'client-123'
        && $request['resource'] === 'https://mcp.example.test/rpc');
});

it('refuses to refresh through an unsafe token endpoint or without a known OAuth client, sending nothing', function (): void {
    Http::fake();
    $unsafe = otrStaleServer(['oauth_token_endpoint' => 'file:///etc/token']);
    $clientless = otrStaleServer(['slug' => 'fixture-2', 'oauth_client_id' => null]);

    expect(fn () => app(OAuthTokenRefresher::class)->refresh($unsafe))
        ->toThrow(McpOAuthException::class, 'unsafe token endpoint')
        ->and(fn () => app(OAuthTokenRefresher::class)->refresh($clientless))
        ->toThrow(McpOAuthException::class, 'No registered OAuth client found for https://auth.example.test');

    Http::assertNothingSent();
});

it('authenticates with the client the server was connected with', function (array $overrides, bool $registered, string $clientId, ?string $secret): void {
    config(['connectors.oauth_apps.gmail.client_id' => 'gmail-app', 'connectors.oauth_apps.gmail.client_secret' => 'rotated-secret']);
    Http::fake(['auth.example.test/token' => Http::response(['access_token' => 'fresh-access'])]);
    if ($registered) {
        ConnectorOAuthClient::query()->create([
            'auth_server_url' => 'https://auth.example.test',
            'client_id' => 'dcr-client',
            'token_endpoint' => 'https://auth.example.test/token',
            'authorization_endpoint' => 'https://auth.example.test/authorize',
        ]);
    }

    app(OAuthTokenRefresher::class)->refresh(otrStaleServer($overrides));

    Http::assertSent(fn (ClientRequest $request): bool => $request['client_id'] === $clientId
        && ($secret === null ? ! array_key_exists('client_secret', $request->data()) : $request['client_secret'] === $secret));
})->with([
    'the connector app, with its current secret' => [['connector_slug' => 'gmail', 'oauth_client_id' => 'gmail-app', 'oauth_client_secret' => 'secret-at-connect'], false, 'gmail-app', 'rotated-secret'],
    'a connector row with another client id' => [['connector_slug' => 'gmail', 'oauth_client_id' => 'workspace-client', 'oauth_client_secret' => 'row-secret'], false, 'workspace-client', 'row-secret'],
    'a custom server' => [['connector_slug' => null, 'oauth_client_id' => 'workspace-client', 'oauth_client_secret' => 'row-secret'], false, 'workspace-client', 'row-secret'],
    'the client registered with the authorization server' => [['oauth_client_id' => null], true, 'dcr-client', null],
]);
