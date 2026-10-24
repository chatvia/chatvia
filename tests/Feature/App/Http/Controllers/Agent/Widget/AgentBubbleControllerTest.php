<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Operations\Translate\TextTranslator;

/**
 * Setup → Chat Bubbles saves the prompts and answers with each one's AI
 * translations into every enabled language. How the widget picks a language
 * is in tests/Unit/Domain/Widget/Actions/BuildWidgetSettingsActionTest.php.
 */
it('saves bubbles via the endpoint and returns the AI translations', function (): void {
    $agent = Agent::factory()->create();
    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()->andReturn(['k0' => ['de' => 'Hallo?']]);

    $this->actingAs($agent->workspace->owner)
        ->putJson(route('agents.bubbles.update', $agent->external_id), [
            'bubbles' => [['text' => 'Hello?']],
        ])
        ->assertOk()
        ->assertJsonPath('bubbles.0.translations.de', 'Hallo?');

    expect($agent->fresh()->settings('widget')->get('bubbles')[0]['text'])->toBe('Hello?');
});
