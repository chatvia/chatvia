<?php

use Domain\Conversation\Models\AgentActivity;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * An agent's tool-call audit log through the public API: only that agent's
 * rows, by public ids, filtered by outcome.
 */
uses(ActsAsApiOwner::class);

it("lists only the agent's rows, by public ids, filtered by outcome", function (): void {
    $this->actAsApiOwner();
    $uri = "/api/v1/agents/{$this->agent->external_id}/audit";
    AgentActivity::factory()->forAgent($this->agent)->create(['tool_name' => 'denied', 'outcome' => 'denied', 'is_gated' => true]);
    AgentActivity::factory()->forAgent($this->agent)->create(['tool_name' => 'allowed']);
    AgentActivity::factory()->forAgent(createAgent($this->owner, ['name' => 'Other']))->create(['tool_name' => 'other']);

    $all = $this->withToken($this->token)->getJson($uri)->assertOk();
    $this->withToken($this->token)
        ->getJson("{$uri}?outcome=denied")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.tool_name', 'denied');

    expect(collect($all->json('data'))->pluck('tool_name')->sort()->values()->all())->toBe(['allowed', 'denied'])
        ->and($all->json('data.0.agent_id'))->toBe($this->agent->external_id)
        ->and($all->json('data.0'))->not->toHaveKey('id');
});
