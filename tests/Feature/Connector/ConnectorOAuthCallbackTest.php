<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Services\OAuth\McpOAuthException;
use Domain\Integration\Connector\Services\OAuth\McpOAuthFlow;
use Domain\Integration\Connector\Services\OAuth\PendingOAuthState;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpCatalog;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

/**
 * Build a connected AgentMcpServer row the mocked flow can hand back, the
 * same way McpOAuthFlow::complete() would after a successful exchange.
 */
function connectedMcpServerFor(User $user, ?string $connectorSlug = null): AgentMcpServer
{
    return $user->mcpServers()->create([
        'name' => 'Weather',
        'slug' => 'weather',
        'connector_slug' => $connectorSlug,
        'url' => 'https://mcp.example.com/weather',
        'auth_type' => 'bearer',
        'auth_value' => 'access-token',
        'is_enabled' => true,
    ]);
}

/**
 * Cache a pending OAuth state the way McpOAuthFlow::begin() does, for a flow
 * started by $starter in a browser session holding $binding.
 */
function stashPendingOAuthState(string $state, User $starter, string $binding): void
{
    $pending = new PendingOAuthState(
        userId: $starter->id,
        connectorSlug: 'notion',
        authServerUrl: 'https://auth.example.test',
        tokenEndpoint: 'https://auth.example.test/token',
        clientId: 'client-123',
        codeVerifier: Str::random(43),
        mcpUrl: 'https://mcp.example.test/mcp',
        sessionBindingHash: PendingOAuthState::hashBinding($binding),
    );

    cache()->put('oauth:mcp:state:'.$state, $pending->seal(), 600);
}

/**
 * A teammate of $owner holding exactly $permissions.
 *
 * @param  list<string>  $permissions
 */
function oauthCallbackTeammate(User $owner, array $permissions): User
{
    $team = $owner->primaryTeam() ?? app(TeamService::class)->createDefaultTeam($owner);

    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Role-'.Str::random(10),
        permissions: $permissions,
        allAgents: true,
        agentExternalIds: [],
    ));

    $member = User::factory()->employee($owner)->create(['current_team_id' => $team->id]);
    $team->members()->attach($member->id, ['role_id' => $role->id]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $member;
}

it('completes the flow and redirects to the connector settings with a success flash', function (): void {
    $server = connectedMcpServerFor($this->user);
    $user = $this->user;

    // The controller hands the flow the signed-in user and this browser
    // session's binding secret.
    $this->mock(McpOAuthFlow::class, function ($mock) use ($server, $user): void {
        $mock->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(fn ($given): bool => $given instanceof User && $given->is($user)), 'binding-1', 'valid-state', 'auth-code')
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
    $this->mock(McpOAuthFlow::class, function ($mock): void {
        $mock->shouldNotReceive('complete');
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

    expect($this->user->mcpServers()->count())->toBe(0);
});

it('rejects a tampered or expired state without connecting anything', function (): void {
    // Real flow, no cached pending state: complete() must refuse the state
    // (CSRF / replay defence) and the controller must turn that into a flash,
    // not a 500.
    $this->actingAs($this->user)
        ->get(route('connectors.oauth.callback', ['state' => 'forged-state', 'code' => 'auth-code']))
        ->assertRedirect(route('integrations.connectors.edit'))
        ->assertSessionHas('error')
        ->assertSessionMissing('success');

    expect($this->user->mcpServers()->count())->toBe(0);
});

it('refuses a state another user started — the victim\'s tokens never reach the attacker\'s workspace', function (): void {
    Http::fake();
    $attacker = User::factory()->create();

    // The attacker starts a flow in their own browser and sends the victim
    // the provider's authorize link; the provider redirects the victim here.
    stashPendingOAuthState('attacker-state', $attacker, 'attacker-binding');

    $this->actingAs($this->user)
        ->get(route('connectors.oauth.callback', ['state' => 'attacker-state', 'code' => 'victim-code']))
        ->assertRedirect(route('integrations.connectors.edit'))
        ->assertSessionHas('error')
        ->assertSessionMissing('success');

    // No code exchange happened and nothing was saved anywhere.
    Http::assertNothingSent();
    expect(AgentMcpServer::query()->whereIn('user_id', [$attacker->id, $this->user->id])->count())->toBe(0);
});

it('refuses a state the same user started in a different browser session', function (): void {
    Http::fake();

    stashPendingOAuthState('other-browser-state', $this->user, 'binding-from-other-browser');

    $this->actingAs($this->user)
        ->withSession(['connectors.oauth.session_binding' => 'binding-in-this-browser'])
        ->get(route('connectors.oauth.callback', ['state' => 'other-browser-state', 'code' => 'auth-code']))
        ->assertRedirect(route('integrations.connectors.edit'))
        ->assertSessionHas('error');

    Http::assertNothingSent();
    expect($this->user->mcpServers()->count())->toBe(0);
});

it('saves a connector a teammate connects under the workspace owner, visible to the whole team', function (): void {
    $owner = User::factory()->create();
    $teammate = oauthCallbackTeammate($owner, ['account.connectors']);

    Http::fake([
        'auth.example.test/token' => Http::response([
            'access_token' => 'fresh-access',
            'refresh_token' => 'fresh-refresh',
            'expires_in' => 3600,
        ]),
    ]);
    $this->mock(McpCatalog::class, fn ($mock) => $mock->shouldReceive('refresh')->andReturn(collect()));

    stashPendingOAuthState('team-state', $teammate, 'team-binding');

    $this->actingAs($teammate)
        ->withSession(['connectors.oauth.session_binding' => 'team-binding'])
        ->get(route('connectors.oauth.callback', ['state' => 'team-state', 'code' => 'auth-code']))
        ->assertRedirect(route('integrations.connectors.edit'))
        ->assertSessionHas('success');

    $server = AgentMcpServer::query()->where('connector_slug', 'notion')->sole();
    expect($server->user_id)->toBe($owner->id)
        ->and($server->auth_value)->toBe('fresh-access');

    // The teammate's connector list (keyed to the owner) shows it.
    $this->actingAs($teammate)
        ->getJson(route('integrations.connectors.data'))
        ->assertOk()
        ->assertJsonFragment(['connector_slug' => 'notion']);
});

it('consumes the state so a second callback with it fails', function (): void {
    Http::fake([
        'auth.example.test/token' => Http::response(['access_token' => 'tok']),
    ]);
    $this->mock(McpCatalog::class, fn ($mock) => $mock->shouldReceive('refresh')->andReturn(collect()));

    stashPendingOAuthState('one-time-state', $this->user, 'binding-1');

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
    $this->mock(McpOAuthFlow::class, function ($mock): void {
        $mock->shouldReceive('complete')
            ->once()
            ->andThrow(new McpOAuthException('The authorization server rejected the token exchange (400).'));
    });

    $this->actingAs($this->user)
        ->get(route('connectors.oauth.callback', ['state' => 'valid-state', 'code' => 'bad-code']))
        ->assertRedirect(route('integrations.connectors.edit'))
        ->assertSessionHas('error');
});

it('redirects home when state or code is missing', function (): void {
    $this->mock(McpOAuthFlow::class, function ($mock): void {
        $mock->shouldNotReceive('complete');
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
