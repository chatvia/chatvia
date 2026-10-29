<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Switching a capability on attaches the workspace's built-in server of it to
 * the agent; Calendar is refused until the workspace connects a scheduler.
 */
beforeEach(function (): void {
    withLicense();
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');
});

it('enables a capability by attaching it to the agent', function (): void {
    $charts = AgentMcpServer::factory()->forOwner($this->owner)->builtin('charts')->create();

    $this->actingAs($this->owner)
        ->postJson(route('agents.capabilities.enable', ['agent' => $this->agent->external_id, 'slug' => 'charts']))
        ->assertOk();

    expect($this->agent->mcpServers()->whereKey($charts->id)->exists())->toBeTrue();
});

it('refuses Calendar until the workspace connects Calendly or Cal.com', function (): void {
    $this->actingAs($this->owner)
        ->postJson(route('agents.capabilities.enable', ['agent' => $this->agent->external_id, 'slug' => 'calendar']))
        ->assertUnprocessable();

    expect($this->agent->mcpServers()->exists())->toBeFalse();
});
