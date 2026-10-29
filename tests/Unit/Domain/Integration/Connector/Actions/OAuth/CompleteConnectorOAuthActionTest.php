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
 * to the same user AND browser session; the code is traded for a token bound
 * to the MCP server (RFC 8707); the server is saved under the workspace,
 * updating the workspace's existing row on a repeat connect, and a custom
 * server under the name and slug it carries; a failing catalog sync is kept
 * on the row instead of undoing a connection that worked.
 */
it('refuses an unknown state or one started by another account or browser, without exchanging the code', function (): void {
    Http::fake();
    $starter = User::factory()->create();
    PendingConnectorOAuth::stash('other-user', $starter, 'binding-a');
    PendingConnectorOAuth::stash('other-browser', $starter, 'binding-a');
    $complete = fn (User $user, string $state, string $binding) => app(CompleteConnectorOAuthAction::class)->execute(
        $user,
        new ConnectorOAuthCallbackData(state: $state, code: 'code', sessionBinding: $binding),
    );

    expect(fn () => $complete($starter, 'not-a-real-state', 'binding-a'))->toThrow(McpOAuthException::class, 'expired or was already used')
        ->and(fn () => $complete(User::factory()->create(), 'other-user', 'binding-a'))->toThrow(McpOAuthException::class, 'different account or browser')
        ->and(fn () => $complete($starter, 'other-browser', 'binding-b'))->toThrow(McpOAuthException::class, 'different account or browser');

    Http::assertNothingSent();
    expect(AgentMcpServer::query()->where('workspace_id', $starter->workspace()->id)->exists())->toBeFalse();
});

it('saves the tokens on the workspace’s server for the teammate who connects, and uses the state once', function (): void {
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
    $existing = AgentMcpServer::factory()->forOwner($owner)->preset('notion')->create([
        'auth_type' => McpAuthType::Bearer,
        'auth_value' => 'expired-access',
        'is_enabled' => false,
        'last_error' => 'The access token expired.',
    ]);
    PendingConnectorOAuth::stash('abc', $teammate, 'binding');
    $callback = new ConnectorOAuthCallbackData(state: 'abc', code: 'code', sessionBinding: 'binding');

    $server = app(CompleteConnectorOAuthAction::class)->execute($teammate, $callback);

    expect(fn () => app(CompleteConnectorOAuthAction::class)->execute($teammate, $callback))
        ->toThrow(McpOAuthException::class, 'expired or was already used');
    expect($server->id)->toBe($existing->id)
        ->and($owner->workspace()->mcpServers()->count())->toBe(1)
        ->and($server->workspace_id)->toBe($owner->workspace()->id)
        ->and($server->connector_slug)->toBe('notion')
        ->and($server->category)->toBe(McpServerCategory::Integration)
        ->and($server->auth_type)->toBe(McpAuthType::Bearer)
        ->and($server->auth_value)->toBe('fresh-access')
        ->and($server->oauth_refresh_token)->toBe('fresh-refresh')
        ->and($server->oauth_client_id)->toBe('client-123')
        ->and($server->oauth_scopes)->toBe('read')
        ->and($server->url)->toBe('https://mcp.example.test/rpc')
        ->and($server->is_enabled)->toBeTrue()
        ->and($server->last_error)->toBeNull();
    Http::assertSentCount(1);
    Http::assertSent(fn (ClientRequest $request): bool => $request['resource'] === 'https://mcp.example.test/rpc');
});

it('keeps a failing catalog sync on a custom server saved under its carried name, instead of undoing the connection', function (): void {
    Http::fake(['auth.example.test/token' => Http::response(['access_token' => 'fresh-access'])]);
    $this->mock(SyncMcpServerCatalogAction::class)->shouldReceive('execute')->andThrow(new RuntimeException('tools/list failed'));
    $user = User::factory()->create();
    PendingConnectorOAuth::stash('abc', $user, 'binding', connectorSlug: null);

    $server = app(CompleteConnectorOAuthAction::class)->execute(
        $user,
        new ConnectorOAuthCallbackData(state: 'abc', code: 'code', sessionBinding: 'binding'),
    );

    expect($server->fresh()?->auth_value)->toBe('fresh-access')
        ->and($server->fresh()?->last_error)->toBe('tools/list failed')
        ->and($server->category)->toBe(McpServerCategory::Custom)
        ->and($server->connector_slug)->toBeNull()
        ->and($server->name)->toBe('My server')
        ->and($server->slug)->toBe('my_server');
});
