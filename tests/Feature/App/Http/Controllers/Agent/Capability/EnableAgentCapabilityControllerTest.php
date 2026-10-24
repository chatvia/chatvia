<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Switching a capability on attaches the workspace's built-in server of it to
 * the agent; a slug that is no capability is a 404.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');
    // The workspace's Charts row exists, so enabling is a pure attach (no
    // remote tools/list during the test).
    $this->charts = AgentMcpServer::factory()->forOwner($this->owner)->builtin('charts')->create();
});

it('enables a capability by attaching it to the agent', function (): void {
    $this->actingAs($this->owner)
        ->postJson(route('agents.capabilities.enable', ['agent' => $this->agent->external_id, 'slug' => 'charts']))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($this->agent->mcpServers()->whereKey($this->charts->id)->exists())->toBeTrue();
});

it('404s for a slug that is not a capability', function (): void {
    $this->actingAs($this->owner)
        ->postJson(route('agents.capabilities.enable', ['agent' => $this->agent->external_id, 'slug' => 'stripe']))
        ->assertNotFound();

    expect($this->agent->mcpServers()->exists())->toBeFalse();
});
