<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Operations\Translate\TextTranslator;
use Mockery\MockInterface;

/**
 * Setup → Starters saves the conversation starters and answers with each one
 * and its AI translations.
 */
it('saves starters and returns their AI translations', function (): void {
    $agent = Agent::factory()->create();
    $this->mock(TextTranslator::class, fn (MockInterface $mock): mixed => $mock->shouldReceive('translate')->once()->andReturn(['k0' => ['de' => 'Hallo?']]));

    $this->actingAs($agent->workspace->owner)
        ->putJson(route('agents.starters.update', $agent->external_id), [
            'starters' => [['text' => 'Hello?', 'icon' => null]],
        ])
        ->assertOk()
        ->assertJsonPath('starters.0.text', 'Hello?')
        ->assertJsonPath('starters.0.translations.de', 'Hallo?');

    expect(array_column($agent->fresh()->starterSet->starters, 'text'))->toBe(['Hello?']);
});
