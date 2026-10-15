<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Membership\Team\Services\TeamService;
use Inertia\Testing\AssertableInertia;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->user->refresh();

    $this->agent = makeTeamAgent($this->user, 'Support Bot');

    // Pre-provision the account-level Charts row so enabling is a pure attach
    // (no remote tools/list during the test).
    $this->charts = AgentMcpServer::factory()->forOwner($this->user)->builtin('charts')->create(['name' => 'Interactive charts']);
});

it('renders the capabilities page with charts and forms (leads folded into forms)', function (): void {
    $this->actingAs($this->user)
        ->get(route('agents.capabilities', $this->agent->external_id))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): AssertableInertia => $page
                ->component('agents/Capabilities/index')
                ->has('capabilities', 2)
                // Ordered by catalog priority: charts (30), forms (40).
                ->where('capabilities.0.connector.slug', 'charts')
                ->where('capabilities.0.enabled', false)
                ->where('capabilities.1.connector.slug', 'forms'),
        );
});

it('enables a capability by attaching it to the agent', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('agents.capabilities.enable', ['agent' => $this->agent->external_id, 'slug' => 'charts']))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($this->agent->mcpServers()->whereKey($this->charts->id)->exists())->toBeTrue();
});

it('disables a capability by detaching it', function (): void {
    $this->agent->mcpServers()->attach($this->charts->id);

    $this->actingAs($this->user)
        ->deleteJson(route('agents.capabilities.disable', ['agent' => $this->agent->external_id, 'slug' => 'charts']))
        ->assertOk();

    expect($this->agent->mcpServers()->whereKey($this->charts->id)->exists())->toBeFalse();
});

it('404s for a slug that is not a capability', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('agents.capabilities.enable', ['agent' => $this->agent->external_id, 'slug' => 'stripe']))
        ->assertNotFound();
});

it('excludes capabilities from the agent connectors list', function (): void {
    $slugs = $this->actingAs($this->user)
        ->getJson(route('agents.sources.connectors.data', $this->agent->external_id))
        ->assertOk()
        ->json('featured.*.connector.slug');

    expect($slugs)->not->toContain('charts')
        ->and($slugs)->not->toContain('forms');
});
