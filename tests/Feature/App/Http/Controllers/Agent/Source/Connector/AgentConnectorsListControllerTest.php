<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * The agent's connectors list: its own connectors with whether each is
 * attached and always on. The built-in capabilities (charts, forms) have
 * their own page, so they are left out.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Tutor Bot');
});

it('reports an attached connector and whether it is always on', function (): void {
    AgentMcpServer::factory()->forOwner($this->owner)->attachedTo($this->agent, alwaysAvailable: true)->create(['slug' => 'hejly-tutor']);

    $this->actingAs($this->owner)
        ->getJson(route('agents.sources.connectors.data', $this->agent->external_id))
        ->assertOk()
        ->assertJsonPath('custom.0.slug', 'hejly-tutor')
        ->assertJsonPath('custom.0.is_attached', true)
        ->assertJsonPath('custom.0.always_available', true);
});

it('leaves the capabilities out of the featured connectors', function (): void {
    AgentMcpServer::factory()->forOwner($this->owner)->builtin('charts')->create();

    $slugs = $this->actingAs($this->owner)
        ->getJson(route('agents.sources.connectors.data', $this->agent->external_id))
        ->assertOk()
        ->json('featured.*.connector.slug');

    expect($slugs)->not->toContain('charts')
        ->and($slugs)->not->toContain('forms');
});
