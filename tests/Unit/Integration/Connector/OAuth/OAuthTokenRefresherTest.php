<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Data\OAuth\PendingOAuthState;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Connector\Services\OAuth\OAuthTokenRefresher;
use Domain\Integration\Mcp\Contracts\AccessTokenRefresher;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Support\Facades\Http;

/**
 * Background token refresh: rotating refresh tokens must not be burned by two
 * workers at once, so a refresh runs under a per-server lock and re-checks
 * staleness after taking it.
 */
function staleOAuthServerFor(User $owner): AgentMcpServer
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

it('is what the MCP client gets for a fresh token', function (): void {
    expect(app(AccessTokenRefresher::class))->toBeInstanceOf(OAuthTokenRefresher::class);
});

it('swaps in a new access token and keeps the rotated refresh token', function (): void {
    Http::fake([
        'auth.example.test/token' => Http::response([
            'access_token' => 'fresh-access',
            'refresh_token' => 'fresh-refresh',
            'expires_in' => 3600,
            'scope' => 'read',
        ]),
    ]);
    $server = staleOAuthServerFor(User::factory()->create());

    app(OAuthTokenRefresher::class)->refresh($server);

    expect($server->fresh()->auth_value)->toBe('fresh-access')
        ->and($server->fresh()->oauth_refresh_token)->toBe('fresh-refresh')
        ->and($server->fresh()->oauth_scopes)->toBe('read')
        ->and($server->fresh()->accessTokenIsStale())->toBeFalse();

    Http::assertSent(fn ($request): bool => $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === 'old-refresh'
        && $request['client_id'] === 'client-123');
});

it('skips the refresh when another worker refreshed while it waited for the lock', function (): void {
    Http::fake();
    $server = staleOAuthServerFor(User::factory()->create());

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
    $server = staleOAuthServerFor(User::factory()->create());

    app(OAuthTokenRefresher::class)->refreshIfStale($server);
    // A second caller holding the same (now fresh) row does nothing.
    app(OAuthTokenRefresher::class)->refreshIfStale($server->fresh());

    Http::assertSentCount(1);
    expect($server->fresh()->auth_value)->toBe('fresh-access');
});

it('refuses to post the refresh token to an unsafe token endpoint', function (): void {
    Http::fake();
    $server = staleOAuthServerFor(User::factory()->create());
    // Private-range checks are relaxed in tests, so use a scheme SafeUrl always refuses.
    $server->forceFill(['oauth_token_endpoint' => 'file:///etc/token'])->save();

    expect(fn () => app(OAuthTokenRefresher::class)->refresh($server))
        ->toThrow(McpOAuthException::class, 'unsafe token endpoint');

    Http::assertNothingSent();
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
