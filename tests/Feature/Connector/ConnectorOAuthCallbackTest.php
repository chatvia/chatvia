<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\OAuth\CompleteConnectorOAuthAction;
use Domain\Integration\Connector\Data\OAuth\ConnectorOAuthCallbackData;
use Domain\Integration\Connector\Exceptions\McpOAuthException;
use Domain\Integration\Mcp\Actions\Catalog\SyncMcpServerCatalogAction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\Support\Integration\Connector\PendingConnectorOAuth;

/**
 * The connector OAuth callback over HTTP: it hands the signed-in user and the
 * browser session's binding to CompleteConnectorOAuthAction (whose state
 * checks are unit-tested) and turns the outcome into a flash on the
 * connector settings.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
});

/**
 * Build a connected AgentMcpServer row the mocked flow can hand back, the
 * same way CompleteConnectorOAuthAction would after a successful exchange.
 */
function connectedMcpServerFor(User $user, ?string $connectorSlug = null): AgentMcpServer
{
    return $user->workspace()->mcpServers()->create([
        'category' => $connectorSlug === null ? 'custom' : 'integration',
        'name' => 'Weather',
        'slug' => 'weather',
        'connector_slug' => $connectorSlug,
        'url' => 'https://mcp.example.com/weather',
        'auth_type' => 'bearer',
        'auth_value' => 'access-token',
        'is_enabled' => true,
    ]);
}

it('completes the flow and redirects to the connector settings with a success flash', function (): void {
    $server = connectedMcpServerFor($this->user);
    $user = $this->user;

    // The controller hands the action the signed-in user and the callback,
    // including this browser session's binding secret.
    $this->mock(CompleteConnectorOAuthAction::class, function (MockInterface $mock) use ($server, $user): void {
        $mock->shouldReceive('execute')
            ->once()
            ->with(
                Mockery::on(fn (User $given): bool => $given->is($user)),
                Mockery::on(fn (ConnectorOAuthCallbackData $callback): bool => $callback->sessionBinding === 'binding-1'
                    && $callback->state === 'valid-state'
                    && $callback->code === 'auth-code'),
            )
            ->andReturn($server);
    });

    $this->actingAs($this->user)
        ->withSession(['connectors.oauth.session_binding' => 'binding-1'])
        ->get(route('connectors.oauth.callback', ['state' => 'valid-state', 'code' => 'auth-code']))
        ->assertRedirect(route('integrations.connectors.edit'))
        ->assertSessionHas('success')
        ->assertSessionMissing('error');
});

it('bounces gracefully with an error flash when the provider denies authorization', function (): void {
    // The user clicked "Deny" upstream — no token exchange may be attempted.
    $this->mock(CompleteConnectorOAuthAction::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('execute');
    });

    $this->actingAs($this->user)
        ->get(route('connectors.oauth.callback', [
            'state' => 'some-state',
            'error' => 'access_denied',
            'error_description' => 'The user denied the request.',
        ]))
        ->assertRedirect(route('integrations.connectors.edit'))
        ->assertSessionHas('error')
        ->assertSessionMissing('success');

    expect($this->user->workspace()->mcpServers()->count())->toBe(0);
});

it('refuses a state another user started — the victim\'s tokens never reach the attacker\'s workspace', function (): void {
    Http::fake();
    $attacker = User::factory()->create();

    // The attacker starts a flow in their own browser and sends the victim
    // the provider's authorize link; the provider redirects the victim here.
    PendingConnectorOAuth::stash('attacker-state', $attacker, 'attacker-binding');

    $this->actingAs($this->user)
        ->get(route('connectors.oauth.callback', ['state' => 'attacker-state', 'code' => 'victim-code']))
        ->assertRedirect(route('integrations.connectors.edit'))
        ->assertSessionHas('error')
        ->assertSessionMissing('success');

    // No code exchange happened and nothing was saved anywhere.
    Http::assertNothingSent();
    expect(AgentMcpServer::query()->whereIn('workspace_id', [$attacker->workspace()->id, $this->user->workspace()->id])->count())->toBe(0);
});

it('saves a connector a teammate connects under the workspace, visible to the whole team', function (): void {
    $owner = User::factory()->create();
    $teammate = teammateWithPermissions($owner, ['account.connectors']);

    Http::fake([
        'auth.example.test/token' => Http::response([
            'access_token' => 'fresh-access',
            'refresh_token' => 'fresh-refresh',
            'expires_in' => 3600,
        ]),
    ]);
    $this->mock(SyncMcpServerCatalogAction::class, function (MockInterface $mock): void {
        $mock->shouldReceive('execute')->andReturn(new Collection);
    });

    PendingConnectorOAuth::stash('team-state', $teammate, 'team-binding');

    $this->actingAs($teammate)
        ->withSession(['connectors.oauth.session_binding' => 'team-binding'])
        ->get(route('connectors.oauth.callback', ['state' => 'team-state', 'code' => 'auth-code']))
        ->assertRedirect(route('integrations.connectors.edit'))
        ->assertSessionHas('success');

    $server = AgentMcpServer::query()->where('connector_slug', 'notion')->sole();
    expect($server->workspace_id)->toBe($owner->workspace()->id)
        ->and($server->auth_value)->toBe('fresh-access');

    // The teammate's connector list (keyed to the workspace) shows it.
    $this->actingAs($teammate)
        ->getJson(route('integrations.connectors.data'))
        ->assertOk()
        ->assertJsonFragment(['connector_slug' => 'notion']);
});

it('consumes the state so a second callback with it fails', function (): void {
    Http::fake([
        'auth.example.test/token' => Http::response(['access_token' => 'tok']),
    ]);
    $this->mock(SyncMcpServerCatalogAction::class, function (MockInterface $mock): void {
        $mock->shouldReceive('execute')->andReturn(new Collection);
    });

    PendingConnectorOAuth::stash('one-time-state', $this->user, 'binding-1');

    $this->actingAs($this->user)
        ->withSession(['connectors.oauth.session_binding' => 'binding-1'])
        ->get(route('connectors.oauth.callback', ['state' => 'one-time-state', 'code' => 'auth-code']))
        ->assertSessionHas('success');

    $this->actingAs($this->user)
        ->withSession(['connectors.oauth.session_binding' => 'binding-1'])
        ->get(route('connectors.oauth.callback', ['state' => 'one-time-state', 'code' => 'auth-code']))
        ->assertSessionHas('error');
});

it('surfaces flow failures during completion as an error flash', function (): void {
    $this->mock(CompleteConnectorOAuthAction::class, function (MockInterface $mock): void {
        $mock->shouldReceive('execute')
            ->once()
            ->andThrow(new McpOAuthException('The authorization server rejected the token exchange (400).'));
    });

    $this->actingAs($this->user)
        ->get(route('connectors.oauth.callback', ['state' => 'valid-state', 'code' => 'bad-code']))
        ->assertRedirect(route('integrations.connectors.edit'))
        ->assertSessionHas('error');
});

it('redirects home when state or code is missing', function (): void {
    $this->mock(CompleteConnectorOAuthAction::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('execute');
    });

    $this->actingAs($this->user)
        ->get(route('connectors.oauth.callback', ['code' => 'auth-code']))
        ->assertRedirect('/')
        ->assertSessionHas('error');
});

it('requires an authenticated user', function (): void {
    $this->get(route('connectors.oauth.callback', ['state' => 's', 'code' => 'c']))
        ->assertRedirect(route('login'));
});
