<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Operations\Starters\StarterGenerator;
use Mockery\MockInterface;

/**
 * Setup → Starters → Generate suggests starters from the agent's knowledge
 * base, as many as asked for within the allowed range.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->owner = $this->agent->workspace->owner;
});

it('suggests starters from the knowledge base', function (): void {
    $this->mock(StarterGenerator::class, fn (MockInterface $mock): mixed => $mock->shouldReceive('generate')->once()
        ->andReturn(['What are your opening hours?', 'How do I get a refund?']));

    $this->actingAs($this->owner)
        ->postJson(route('agents.starters.generate', $this->agent->external_id), ['count' => 2])
        ->assertOk()
        ->assertJsonCount(2, 'suggestions')
        ->assertJsonPath('suggestions.0', 'What are your opening hours?');
});

it('rejects an out-of-range starter count', function (): void {
    $this->mock(StarterGenerator::class)->shouldNotReceive('generate');

    $this->actingAs($this->owner)
        ->postJson(route('agents.starters.generate', $this->agent->external_id), ['count' => 99])
        ->assertJsonValidationErrors('count');
});
