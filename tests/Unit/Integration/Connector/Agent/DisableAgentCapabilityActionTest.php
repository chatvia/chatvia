<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\Agent\DisableAgentCapabilityAction;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Membership\Team\Services\TeamService;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->agent = makeTeamAgent($this->user, 'Support Bot');

    foreach (['forms', 'leads'] as $slug) {
        $server = $this->user->mcpServers()->create([
            'connector_slug' => $slug,
            'category' => 'builtin',
            'name' => ucfirst($slug),
            'slug' => $slug,
            'url' => config('app.url').'/mcp/'.$slug,
            'auth_type' => 'none',
            'is_enabled' => true,
        ]);
        $this->agent->mcpServers()->attach($server->id);
    }
});

it('detaches the capability and its companions from the agent', function (): void {
    expect($this->agent->isFormsEnabled())->toBeTrue()
        ->and($this->agent->isLeadsEnabled())->toBeTrue();

    $forms = app(ConnectorCatalog::class)->capabilities()->get('forms');
    app(DisableAgentCapabilityAction::class)->execute($this->agent, $forms);

    expect($this->agent->mcpServers()->where('connector_slug', 'forms')->exists())->toBeFalse()
        ->and($this->agent->mcpServers()->where('connector_slug', 'leads')->exists())->toBeFalse();
});

it('leaves the account-level server rows intact (detaches the pivot only)', function (): void {
    $forms = app(ConnectorCatalog::class)->capabilities()->get('forms');
    app(DisableAgentCapabilityAction::class)->execute($this->agent, $forms);

    expect(AgentMcpServer::query()->where('connector_slug', 'forms')->where('user_id', $this->user->id)->exists())->toBeTrue()
        ->and(AgentMcpServer::query()->where('connector_slug', 'leads')->where('user_id', $this->user->id)->exists())->toBeTrue();
});
