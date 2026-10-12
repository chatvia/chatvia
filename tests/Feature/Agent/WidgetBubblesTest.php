<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Operations\Translate\TextTranslator;
use Domain\Widget\Actions\BuildWidgetSettingsAction;

/**
 * The Setup → Chat Bubbles endpoint auto-translates each prompt into every
 * enabled language, and the widget settings payload serves the bubbles localized
 * to the visitor's language (override > translation > base).
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

it('serves the localized bubbles in the widget payload', function (): void {
    $this->agent->setSetting('bubbles', [
        ['text' => 'Hello', 'translations' => ['de' => 'Hallo'], 'overrides' => []],
        ['text' => 'Bye', 'translations' => ['de' => 'Tschüss'], 'overrides' => ['de' => 'Ciao']],
    ], 'widget');

    $settings = app(BuildWidgetSettingsAction::class)->execute($this->agent->fresh(), 'de-DE,de;q=0.9');

    expect($settings->bubbles)->toBe(['Hallo', 'Ciao']);
});

it('keeps the base bubbles when the visitor language has no translation', function (): void {
    $this->agent->setSetting('bubbles', [
        ['text' => 'Hello', 'translations' => ['de' => 'Hallo'], 'overrides' => []],
    ], 'widget');

    $settings = app(BuildWidgetSettingsAction::class)->execute($this->agent->fresh(), 'it-IT');

    expect($settings->bubbles)->toBe(['Hello']);
});
