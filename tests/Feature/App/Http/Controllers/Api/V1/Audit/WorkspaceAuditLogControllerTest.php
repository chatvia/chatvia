<?php

use Domain\Conversation\Models\AgentActivity;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * The workspace's tool-call audit log through the public API: the rows of
 * every agent of the workspace.
 */
uses(ActsAsApiOwner::class);

it('lists the rows of every agent of the workspace', function (): void {
    $this->actAsApiOwner();
    AgentActivity::factory()->forAgent($this->agent)->create(['tool_name' => 'mine']);
    AgentActivity::factory()->forAgent(createAgent($this->owner, ['name' => 'Other']))->create(['tool_name' => 'other']);

    $response = $this->withToken($this->token)->getJson('/api/v1/audit')->assertOk();

    expect(collect($response->json('data'))->pluck('tool_name')->sort()->values()->all())->toBe(['mine', 'other']);
});
