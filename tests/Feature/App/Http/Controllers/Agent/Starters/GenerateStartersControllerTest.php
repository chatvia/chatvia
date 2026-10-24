<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Exceptions\LlmCallFailedException;
use Domain\Llm\Exceptions\NoLlmAvailableException;
use Domain\Llm\Operations\Starters\StarterGenerator;
use Mockery\MockInterface;

/**
 * Setup → Starters → Generate suggests starters from the agent's knowledge
 * base, as many as asked for within the allowed range. When the model cannot
 * answer, the dialog gets a message that says why.
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

it('says why when the model refuses the call', function (): void {
    $exception = LlmCallFailedException::refused(testLlm('qwen/qwen3-30b'), 404, 'No allowed providers are available for the selected model.', new RuntimeException);
    $this->mock(StarterGenerator::class, fn (MockInterface $mock): mixed => $mock->shouldReceive('generate')->once()->andThrow($exception));

    $this->actingAs($this->owner)
        ->postJson(route('agents.starters.generate', $this->agent->external_id), ['count' => 5])
        ->assertStatus(502)
        ->assertJsonPath('message', $exception->getMessage());
});

it('says why when no model is set up', function (): void {
    $this->mock(StarterGenerator::class, fn (MockInterface $mock): mixed => $mock->shouldReceive('generate')->once()->andThrow(new NoLlmAvailableException));

    $this->actingAs($this->owner)
        ->postJson(route('agents.starters.generate', $this->agent->external_id), ['count' => 5])
        ->assertStatus(503)
        ->assertJsonPath('message', (new NoLlmAvailableException)->getMessage());
});
