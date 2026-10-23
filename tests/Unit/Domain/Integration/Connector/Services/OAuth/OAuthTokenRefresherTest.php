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
 * staleness after taking it. It authenticates with the client the server was
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

it('swaps in a new access token and keeps the rotated refresh token', function (): void {
    Http::fake([
        'auth.example.test/token' => Http::response([
            'access_token' => 'fresh-access',
            'refresh_token' => 'fresh-refresh',
            'expires_in' => 3600,
            'scope' => 'read',
        ]),
    ]);
    $server = otrStaleServer();

    app(OAuthTokenRefresher::class)->refresh($server);

    expect($server->fresh()->auth_value)->toBe('fresh-access')
        ->and($server->fresh()->oauth_refresh_token)->toBe('fresh-refresh')
        ->and($server->fresh()->oauth_scopes)->toBe('read')
        ->and($server->fresh()->accessTokenIsStale())->toBeFalse();

    Http::assertSent(fn (ClientRequest $request): bool => $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === 'old-refresh'
        && $request['client_id'] === 'client-123');
});

it('skips the refresh when another worker refreshed while it waited for the lock', function (): void {
    Http::fake();
    $server = otrStaleServer();

    // Another worker already rotated the tokens in the database; our copy is stale.
    AgentMcpServer::query()->whereKey($server->id)->first()->forceFill([
        'auth_value' => 'new-access',
        'oauth_refresh_token' => 'new-refresh',
        'oauth_expires_at' => now()->addHour(),
    ])->save();

    app(OAuthTokenRefresher::class)->refreshIfStale($server);

    Http::assertNothingSent();
    expect($server->auth_value)->toBe('new-access')
        ->and($server->oauth_refresh_token)->toBe('new-refresh');
});

it('refreshes a stale token once under the lock', function (): void {
    Http::fake([
        'auth.example.test/token' => Http::response([
            'access_token' => 'fresh-access',
            'refresh_token' => 'fresh-refresh',
            'expires_in' => 3600,
        ]),
    ]);
    $server = otrStaleServer();

    app(OAuthTokenRefresher::class)->refreshIfStale($server);
    // A second caller holding the same (now fresh) row does nothing.
    app(OAuthTokenRefresher::class)->refreshIfStale($server->fresh());

    Http::assertSentCount(1);
    expect($server->fresh()->auth_value)->toBe('fresh-access');
});

it('refuses to post the refresh token to an unsafe token endpoint', function (): void {
    Http::fake();
    $server = otrStaleServer();
    // Private-range checks are relaxed in tests, so use a scheme SafeUrl always refuses.
    $server->forceFill(['oauth_token_endpoint' => 'file:///etc/token'])->save();

    expect(fn () => app(OAuthTokenRefresher::class)->refresh($server))
        ->toThrow(McpOAuthException::class, 'unsafe token endpoint');

    Http::assertNothingSent();
});

it('sends the connector app\'s current secret for a row connected with that app', function (): void {
    config(['connectors.gmail.oauth_client_id' => 'gmail-app', 'connectors.gmail.oauth_client_secret' => 'rotated-secret']);
    Http::fake(['auth.example.test/token' => Http::response(['access_token' => 'fresh-access'])]);
    $server = otrStaleServer(['connector_slug' => 'gmail', 'oauth_client_id' => 'gmail-app', 'oauth_client_secret' => 'secret-at-connect']);

    app(OAuthTokenRefresher::class)->refresh($server);

    Http::assertSent(fn (ClientRequest $request): bool => $request['client_id'] === 'gmail-app'
        && $request['client_secret'] === 'rotated-secret');
});

it('sends the row\'s own secret for a client the connector app did not issue', function (?string $connectorSlug): void {
    config(['connectors.gmail.oauth_client_id' => 'gmail-app', 'connectors.gmail.oauth_client_secret' => 'app-secret']);
    Http::fake(['auth.example.test/token' => Http::response(['access_token' => 'fresh-access'])]);
    $server = otrStaleServer(['connector_slug' => $connectorSlug, 'oauth_client_id' => 'workspace-client', 'oauth_client_secret' => 'row-secret']);

    app(OAuthTokenRefresher::class)->refresh($server);

    Http::assertSent(fn (ClientRequest $request): bool => $request['client_id'] === 'workspace-client'
        && $request['client_secret'] === 'row-secret');
})->with([
    'a connector row with another client id' => ['gmail'],
    'a custom server' => [null],
]);

it('falls back to the client registered with the authorization server', function (): void {
    Http::fake(['auth.example.test/token' => Http::response(['access_token' => 'fresh-access'])]);
    ConnectorOAuthClient::query()->create([
        'auth_server_url' => 'https://auth.example.test',
        'client_id' => 'dcr-client',
        'token_endpoint' => 'https://auth.example.test/token',
        'authorization_endpoint' => 'https://auth.example.test/authorize',
    ]);
    $server = otrStaleServer(['oauth_client_id' => null]);

    app(OAuthTokenRefresher::class)->refresh($server);

    Http::assertSent(fn (ClientRequest $request): bool => $request['client_id'] === 'dcr-client'
        && ! array_key_exists('client_secret', $request->data()));
});

it('refuses to refresh when no OAuth client is known for the server', function (): void {
    Http::fake();
    $server = otrStaleServer(['oauth_client_id' => null]);

    expect(fn () => app(OAuthTokenRefresher::class)->refresh($server))
        ->toThrow(McpOAuthException::class, 'No registered OAuth client found for https://auth.example.test');

    Http::assertNothingSent();
});
