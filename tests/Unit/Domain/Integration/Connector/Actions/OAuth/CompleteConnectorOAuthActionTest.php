<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\OAuth\CompleteConnectorOAuthAction;
use Domain\Integration\Connector\Data\OAuth\ConnectorOAuthCallbackData;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Mcp\Actions\Catalog\SyncMcpServerCatalogAction;
use Domain\Integration\Mcp\Enums\McpAuthType;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\Support\Integration\Connector\PendingConnectorOAuth;

/**
 * Finishing a connector OAuth flow: the state is single-use and must belong
 * to the same user AND browser session; the server is saved under the
 * workspace; a failing catalog sync is kept on the row instead of
 * undoing a connection that worked.
 */
it('rejects an unknown or already-used state', function (): void {
    Http::fake();

    expect(fn () => app(CompleteConnectorOAuthAction::class)->execute(
        User::factory()->create(),
        new ConnectorOAuthCallbackData(state: 'not-a-real-state', code: 'code', sessionBinding: 'binding'),
    ))->toThrow(McpOAuthException::class, 'expired or was already used');

    Http::assertNothingSent();
});

it('rejects a callback from another account or browser session without exchanging the code', function (bool $sameUser, string $binding): void {
    Http::fake();
    $starter = User::factory()->create();
    PendingConnectorOAuth::stash('abc', $starter, 'binding-a');

    expect(fn () => app(CompleteConnectorOAuthAction::class)->execute(
        $sameUser ? $starter : User::factory()->create(),
        new ConnectorOAuthCallbackData(state: 'abc', code: 'code', sessionBinding: $binding),
    ))->toThrow(McpOAuthException::class, 'different account or browser');

    Http::assertNothingSent();
    expect(AgentMcpServer::query()->where('workspace_id', $starter->workspace()->id)->exists())->toBeFalse();
})->with([
    'another user, same binding' => [false, 'binding-a'],
    'the same user, another browser' => [true, 'binding-b'],
]);

it('saves the tokens under the workspace of the teammate who connects', function (): void {
    Http::fake([
        'auth.example.test/token' => Http::response([
            'access_token' => 'fresh-access',
            'refresh_token' => 'fresh-refresh',
            'expires_in' => 3600,
            'scope' => 'read',
        ]),
    ]);
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')->andReturn(new Collection);
    $owner = User::factory()->create();
    $teammate = User::factory()->employee($owner)->create();
    PendingConnectorOAuth::stash('abc', $teammate, 'binding');

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
        ->and($server->last_error)->toBeNull();
});

it('keeps a failing catalog sync on the row instead of undoing the connection', function (): void {
    Http::fake(['auth.example.test/token' => Http::response(['access_token' => 'fresh-access'])]);
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')->andThrow(new RuntimeException('tools/list failed'));
    $user = User::factory()->create();
    PendingConnectorOAuth::stash('abc', $user, 'binding');

    $server = app(CompleteConnectorOAuthAction::class)->execute(
        $user,
        new ConnectorOAuthCallbackData(state: 'abc', code: 'code', sessionBinding: 'binding'),
    );

    expect($server->fresh()?->auth_value)->toBe('fresh-access')
        ->and($server->fresh()?->last_error)->toBe('tools/list failed');
});

it('uses a state once: the same callback cannot complete twice', function (): void {
    Http::fake(['auth.example.test/token' => Http::response(['access_token' => 'fresh-access'])]);
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')->andReturn(new Collection);
    $user = User::factory()->create();
    PendingConnectorOAuth::stash('abc', $user, 'binding');
    $callback = new ConnectorOAuthCallbackData(state: 'abc', code: 'code', sessionBinding: 'binding');
    app(CompleteConnectorOAuthAction::class)->execute($user, $callback);

    expect(fn () => app(CompleteConnectorOAuthAction::class)->execute($user, $callback))
        ->toThrow(McpOAuthException::class, 'expired or was already used');

    Http::assertSentCount(1);
    // RFC 8707: the code is traded for a token bound to this MCP server.
    Http::assertSent(fn (ClientRequest $request): bool => $request['resource'] === 'https://mcp.example.test/rpc');
});

it('saves a custom server under its carried name and slug', function (): void {
    Http::fake(['auth.example.test/token' => Http::response(['access_token' => 'tok'])]);
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')->andReturn(new Collection);

    $user = User::factory()->create();
    PendingConnectorOAuth::stash('abc', $user, 'binding', connectorSlug: null);

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
    PendingConnectorOAuth::stash('abc', $user, 'binding');

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
