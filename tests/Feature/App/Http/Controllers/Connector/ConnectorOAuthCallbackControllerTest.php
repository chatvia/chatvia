<?php

declare(strict_types=1);

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

it('completes the flow and redirects to the connector settings with a success flash', function (): void {
    $server = AgentMcpServer::factory()->forOwner($this->user)->create(['name' => 'Weather']);
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
        ->assertSessionHas('success', 'Weather connected.')
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
        ->assertSessionHas('error', 'The user denied the request.')
        ->assertSessionMissing('success');

    expect($this->user->workspace()->mcpServers()->count())->toBe(0);
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

it('surfaces flow failures during completion as an error flash', function (): void {
    $this->mock(CompleteConnectorOAuthAction::class, function (MockInterface $mock): void {
        $mock->shouldReceive('execute')
            ->once()
            ->andThrow(new McpOAuthException('The authorization server rejected the token exchange (400).'));
    });

    $this->actingAs($this->user)
        ->get(route('connectors.oauth.callback', ['state' => 'valid-state', 'code' => 'bad-code']))
        ->assertRedirect(route('integrations.connectors.edit'))
        ->assertSessionHas('error', 'The authorization server rejected the token exchange (400).');
});

it('redirects home when the state or the code is missing', function (array $query): void {
    $this->mock(CompleteConnectorOAuthAction::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('execute');
    });

    $this->actingAs($this->user)
        ->get(route('connectors.oauth.callback', $query))
        ->assertRedirect('/')
        ->assertSessionHas('error');
})->with([
    'no state' => [['code' => 'auth-code']],
    'no code' => [['state' => 'valid-state']],
]);
