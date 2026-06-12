<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Services\OAuth\McpOAuthException;
use Domain\Integration\Connector\Services\OAuth\McpOAuthFlow;
use Domain\Integration\Mcp\Models\AgentMcpServer;

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

it('completes the flow and redirects to the connector settings with a success flash', function (): void {
    $server = connectedMcpServerFor($this->user);

    $this->mock(McpOAuthFlow::class, function ($mock) use ($server): void {
        $mock->shouldReceive('complete')
            ->once()
            ->with('valid-state', 'auth-code')
            ->andReturn($server);
    });

    $this->actingAs($this->user)
        ->get(route('connectors.oauth.callback', ['state' => 'valid-state', 'code' => 'auth-code']))
        ->assertRedirect(route('integrations.connectors.edit'))
        ->assertSessionHas('success')
        ->assertSessionMissing('error');
});

it('redirects back to the agent connectors page when the flow carries an auto-attach intent', function (): void {
    $agent = makeTeamAgent($this->user, 'Support Bot');
    $server = connectedMcpServerFor($this->user);

    // The pending state McpOAuthFlow::begin() stashed, including the agent
    // the user started from. The controller peeks it (get, not pull) to pick
    // the success landing page.
    cache()->put('oauth:mcp:state:agent-state', [
        'auto_attach_agent_id' => $agent->id,
    ], 600);

    $this->mock(McpOAuthFlow::class, function ($mock) use ($server): void {
        $mock->shouldReceive('complete')
            ->once()
            ->with('agent-state', 'auth-code')
            ->andReturn($server);
    });

    $this->actingAs($this->user)
        ->get(route('connectors.oauth.callback', ['state' => 'agent-state', 'code' => 'auth-code']))
        ->assertRedirect(route('agents.sources.connectors.index', $agent))
        ->assertSessionHas('success');
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
