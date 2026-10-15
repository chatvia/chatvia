<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\OAuth\CompleteConnectorOAuthAction;
use Domain\Integration\Connector\Data\OAuth\ConnectorOAuthCallbackData;
use Domain\Integration\Connector\Data\OAuth\PendingOAuthState;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Mcp\Actions\Catalog\SyncMcpServerCatalogAction;
use Domain\Integration\Mcp\Enums\McpAuthType;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Finishing a connector OAuth flow: the state is single-use and must belong
 * to the same user AND browser session; the server is saved under the
 * workspace; a failing catalog sync is kept on the row instead of
 * undoing a connection that worked.
 */
beforeEach(function (): void {
    config(['cache.default' => 'array']);
});

function stashCompletablePendingState(string $state, User $starter, string $binding, ?string $connectorSlug = 'notion'): void
{
    Cache::put('oauth:mcp:state:'.$state, new PendingOAuthState(
        userId: $starter->id,
        connectorSlug: $connectorSlug,
        authServerUrl: 'https://auth.example.test',
        tokenEndpoint: 'https://auth.example.test/token',
        clientId: 'client-123',
        codeVerifier: 'verifier',
        mcpUrl: 'https://mcp.example.test/rpc',
        customName: $connectorSlug === null ? 'My server' : null,
        customSlug: $connectorSlug === null ? 'my_server' : null,
        sessionBindingHash: PendingOAuthState::hashBinding($binding),
    )->seal(), 600);
}

it('rejects an unknown or already-used state', function (): void {
    Http::fake();

    expect(fn () => app(CompleteConnectorOAuthAction::class)->execute(
        User::factory()->create(),
        new ConnectorOAuthCallbackData(state: 'not-a-real-state', code: 'code', sessionBinding: 'binding'),
    ))->toThrow(McpOAuthException::class, 'expired or was already used');

    Http::assertNothingSent();
});

it('rejects a callback from a different user without exchanging the code', function (): void {
    Http::fake();
    stashCompletablePendingState('abc', User::factory()->create(), 'binding');

    expect(fn () => app(CompleteConnectorOAuthAction::class)->execute(
        User::factory()->create(),
        new ConnectorOAuthCallbackData(state: 'abc', code: 'code', sessionBinding: 'binding'),
    ))->toThrow(McpOAuthException::class, 'different account or browser');

    Http::assertNothingSent();
});

it('rejects a callback from another browser session of the same user', function (): void {
    Http::fake();
    $user = User::factory()->create();
    stashCompletablePendingState('abc', $user, 'binding-a');

    expect(fn () => app(CompleteConnectorOAuthAction::class)->execute(
        $user,
        new ConnectorOAuthCallbackData(state: 'abc', code: 'code', sessionBinding: 'binding-b'),
    ))->toThrow(McpOAuthException::class, 'different account or browser');

    Http::assertNothingSent();
});

it('saves the tokens under the workspace owner and keeps a catalog failure on the row', function (): void {
    Http::fake([
        'auth.example.test/token' => Http::response([
            'access_token' => 'fresh-access',
            'refresh_token' => 'fresh-refresh',
            'expires_in' => 3600,
            'scope' => 'read',
        ]),
    ]);
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')->andThrow(new RuntimeException('tools/list failed'));

    $owner = User::factory()->create();
    $teammate = User::factory()->employee($owner)->create();
    stashCompletablePendingState('abc', $teammate, 'binding');

    $server = app(CompleteConnectorOAuthAction::class)->execute(
        $teammate,
        new ConnectorOAuthCallbackData(state: 'abc', code: 'code', sessionBinding: 'binding'),
    );

    expect($server->workspace_id)->toBe($owner->workspace()->id)
        ->and($server->connector_slug)->toBe('notion')
        ->and($server->category)->toBe(McpServerCategory::Integration)
        ->and($server->auth_type)->toBe(McpAuthType::Bearer)
        ->and($server->auth_value)->toBe('fresh-access')
        ->and($server->oauth_refresh_token)->toBe('fresh-refresh')
        ->and($server->oauth_client_id)->toBe('client-123')
        ->and($server->oauth_scopes)->toBe('read')
        ->and($server->timeout_ms)->toBe(AgentMcpServer::DEFAULT_TIMEOUT_MS)
        ->and($server->last_error)->toBe('tools/list failed');

    // Single use: the same state cannot complete twice.
    expect(fn () => app(CompleteConnectorOAuthAction::class)->execute(
        $teammate,
        new ConnectorOAuthCallbackData(state: 'abc', code: 'code', sessionBinding: 'binding'),
    ))->toThrow(McpOAuthException::class);
});

it('saves a custom server under its carried name and slug', function (): void {
    Http::fake(['auth.example.test/token' => Http::response(['access_token' => 'tok'])]);
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')->andReturn(new Collection);

    $user = User::factory()->create();
    stashCompletablePendingState('abc', $user, 'binding', connectorSlug: null);

    $server = app(CompleteConnectorOAuthAction::class)->execute(
        $user,
        new ConnectorOAuthCallbackData(state: 'abc', code: 'code', sessionBinding: 'binding'),
    );

    expect($server->connector_slug)->toBeNull()
        ->and($server->category)->toBe(McpServerCategory::Custom)
        ->and($server->name)->toBe('My server')
        ->and($server->slug)->toBe('my_server')
        ->and($server->oauth_expires_at)->toBeNull();
});

it('updates the workspace\'s existing server on a repeat connect', function (): void {
    Http::fake(['auth.example.test/token' => Http::response(['access_token' => 'fresh-access', 'refresh_token' => 'fresh-refresh'])]);
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')->andReturn(new Collection);

    $user = User::factory()->create();
    $existing = AgentMcpServer::factory()->forOwner($user)->preset('notion')->create([
        'auth_type' => McpAuthType::Bearer,
        'auth_value' => 'expired-access',
        'is_enabled' => false,
        'last_error' => 'The access token expired.',
    ]);
    stashCompletablePendingState('abc', $user, 'binding');

    $server = app(CompleteConnectorOAuthAction::class)->execute(
        $user,
        new ConnectorOAuthCallbackData(state: 'abc', code: 'code', sessionBinding: 'binding'),
    );

    expect($server->id)->toBe($existing->id)
        ->and($user->workspace()->mcpServers()->count())->toBe(1)
        ->and($server->auth_value)->toBe('fresh-access')
        ->and($server->oauth_refresh_token)->toBe('fresh-refresh')
        ->and($server->url)->toBe('https://mcp.example.test/rpc')
        ->and($server->is_enabled)->toBeTrue()
        ->and($server->last_error)->toBeNull();
});
