<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Operations\Starters\StarterGenerator;
use Domain\Llm\Operations\Translate\TextTranslator;
use Mockery\MockInterface;

/**
 * The Setup → Starters endpoints: saving auto-translates each starter and
 * returns the items; the generate endpoint suggests starters from the agent's
 * knowledge base.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->owner = $this->agent->workspace->owner;
});

it('saves starters and returns their AI translations', function (): void {
    $this->mock(TextTranslator::class, fn (MockInterface $mock): mixed => $mock->shouldReceive('translate')->once()->andReturn(['k0' => ['de' => 'Hallo?']]));

    $this->actingAs($this->owner)
        ->putJson(route('agents.starters.update', $this->agent->external_id), [
            'starters' => [['text' => 'Hello?', 'icon' => null]],
        ])
        ->assertOk()
        ->assertJsonPath('starters.0.text', 'Hello?')
        ->assertJsonPath('starters.0.translations.de', 'Hallo?');
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
    $this->actingAs($this->owner)
        ->postJson(route('agents.starters.generate', $this->agent->external_id), ['count' => 99])
        ->assertStatus(422);
});
