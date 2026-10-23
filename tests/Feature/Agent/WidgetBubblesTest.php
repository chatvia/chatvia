<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Operations\Translate\TextTranslator;

/**
 * The Setup → Chat Bubbles endpoint saves the prompts and auto-translates each
 * one into every enabled language. How the widget picks a language is in
 * tests/Unit/Domain/Widget/BuildWidgetSettingsActionTest.php.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->owner = $this->agent->workspace->owner;
});

it('saves bubbles via the endpoint and returns the AI translations', function (): void {
    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()->andReturn(['k0' => ['de' => 'Hallo?']]);

    $this->actingAs($this->owner)
        ->putJson(route('agents.bubbles.update', $this->agent->external_id), [
            'bubbles' => [['text' => 'Hello?']],
        ])
        ->assertOk()
        ->assertJsonPath('bubbles.0.translations.de', 'Hallo?');

    expect($this->agent->fresh()->settings('widget')->get('bubbles')[0]['text'])->toBe('Hello?');
});
