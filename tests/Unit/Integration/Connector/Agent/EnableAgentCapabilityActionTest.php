<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\Agent\EnableAgentCapabilityAction;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Connector\Services\TokenConnectorAuthorizer;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Membership\Team\Services\TeamService;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->agent = makeTeamAgent($this->user, 'Support Bot');
    $this->charts = app(ConnectorCatalog::class)->capabilities()->get('charts');
});

it('attaches an already-provisioned capability without re-provisioning', function (): void {
    $server = $this->user->mcpServers()->create([
        'connector_slug' => 'charts',
        'category' => 'builtin',
        'name' => 'Interactive charts',
        'slug' => 'charts',
        'url' => config('app.url').'/mcp/charts',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);

    // No account-level provisioning should happen — the row already exists, so
    // the authorizer (which does a remote tools/list) must never be called.
    $this->mock(TokenConnectorAuthorizer::class)
        ->shouldReceive('connect')->never();

    $result = app(EnableAgentCapabilityAction::class)->execute($this->agent, $this->user, $this->charts);

    expect($result->id)->toBe($server->id)
        ->and($this->agent->mcpServers()->whereKey($server->id)->exists())->toBeTrue()
        ->and(AgentMcpServer::query()->where('connector_slug', 'charts')->count())->toBe(1);
});

it('also attaches companion servers (Forms companions the hidden leads server)', function (): void {
    // Pre-provision both rows so no remote provisioning happens.
    foreach (['forms', 'leads'] as $slug) {
        $this->user->mcpServers()->create([
            'connector_slug' => $slug,
            'category' => 'builtin',
            'name' => ucfirst($slug),
            'slug' => $slug,
            'url' => config('app.url').'/mcp/'.$slug,
            'auth_type' => 'none',
            'is_enabled' => true,
        ]);
    }

    $this->mock(TokenConnectorAuthorizer::class)->shouldReceive('connect')->never();

    $forms = app(ConnectorCatalog::class)->capabilities()->get('forms');
    expect($forms->companions)->toBe(['leads']);

    app(EnableAgentCapabilityAction::class)->execute($this->agent, $this->user, $forms);

    expect($this->agent->mcpServers()->where('connector_slug', 'forms')->exists())->toBeTrue()
        ->and($this->agent->mcpServers()->where('connector_slug', 'leads')->exists())->toBeTrue()
        ->and($this->agent->isLeadsEnabled())->toBeTrue(); // → Leads inbox/settings now visible
});

it('provisions the capability on first enable, then attaches it', function (): void {
    // No 'charts' row exists, so the action's own query finds nothing and it
    // provisions via the authorizer. The persisted row carries a null
    // connector_slug so that query can't match it; the stubbed authorizer
    // hands it back as the freshly provisioned server (keeps the test offline).
    $provisioned = $this->user->mcpServers()->create([
        'connector_slug' => null,
        'category' => 'builtin',
        'name' => 'Interactive charts',
        'slug' => 'charts',
        'url' => config('app.url').'/mcp/charts',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);

    $this->mock(TokenConnectorAuthorizer::class)
        ->shouldReceive('connect')
        ->once()
        ->andReturn(['server' => $provisioned, 'tools_count' => 12]);

    app(EnableAgentCapabilityAction::class)->execute($this->agent, $this->user, $this->charts);

    expect($this->agent->mcpServers()->whereKey($provisioned->id)->exists())->toBeTrue();
});
