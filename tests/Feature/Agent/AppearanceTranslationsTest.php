<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Operations\Translate\TextTranslator;
use Domain\Widget\Actions\BuildWidgetSettingsAction;

/**
 * The Setup appearance-text endpoints: saving auto-translates the three strings
 * into every supported language, manual overrides persist separately, and the
 * chat surfaces serve the localized copy by visitor language.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->owner = $this->agent->user;
});

it('saves the base text and returns the AI translations', function (): void {
    $map = [
        'chat_window_title' => ['de' => 'Chat'],
        'welcome_message' => ['de' => 'Hallo, wie kann ich helfen?'],
        'composer_placeholder' => ['de' => 'Frag mich etwas …'],
    ];

    $this->mock(TextTranslator::class, fn ($m) => $m->shouldReceive('translate')->once()->andReturn($map));

    $this->actingAs($this->owner)
        ->postJson(route('agents.appearance.text', $this->agent->external_id), [
            'chat_window_title' => 'Chat',
            'welcome_message' => 'Hello, how can I help?',
            'composer_placeholder' => 'Ask anything…',
        ])
        ->assertOk()
        ->assertJsonPath('translations.welcome_message.de', 'Hallo, wie kann ich helfen?');

    $appearance = $this->agent->fresh()->settings('appearance');

    expect($appearance->get('welcome_message'))->toBe('Hello, how can I help?')
        ->and($appearance->get('text_translations'))->toBe($map);
});

it('stores manual per-language overrides and drops empty values', function (): void {
    $this->actingAs($this->owner)
        ->putJson(route('agents.appearance.translations.update', $this->agent->external_id), [
            'overrides' => [
                'welcome_message' => ['de' => 'Servus!', 'fr' => ''],
            ],
        ])
        ->assertOk();

    expect($this->agent->fresh()->settings('appearance')->get('text_overrides'))
        ->toBe(['welcome_message' => ['de' => 'Servus!']]);
});

it('rejects an oversized override value', function (): void {
    $this->actingAs($this->owner)
        ->putJson(route('agents.appearance.translations.update', $this->agent->external_id), [
            'overrides' => [
                'welcome_message' => ['de' => str_repeat('x', 501)],
            ],
        ])
        ->assertStatus(422);
});

it('persists the owner-selected UI languages through the appearance settings endpoint', function (): void {
    $this->actingAs($this->owner)
        ->post(route('agents.settings.update', ['agent' => $this->agent->external_id, 'group' => 'appearance']), [
            'enabled_languages' => ['de', 'fr'],
        ])
        ->assertSessionHasNoErrors();

    $fresh = $this->agent->fresh();

    expect($fresh->settings('appearance')->get('enabled_languages'))->toBe(['de', 'fr'])
        ->and(array_keys($fresh->enabledLanguages()))->toBe(['de', 'fr']);
});

it('serves the localized welcome message in the widget payload', function (): void {
    $this->agent->setSetting('welcome_message', 'Hello', 'appearance');
    $this->agent->setSetting('text_translations', ['welcome_message' => ['de' => 'Hallo']], 'appearance');

    $settings = app(BuildWidgetSettingsAction::class)->execute($this->agent->fresh(), 'de-DE,de;q=0.9');

    expect($settings->welcome_message)->toBe('Hallo');
});

it('keeps the base welcome message when the visitor language has no translation', function (): void {
    $this->agent->setSetting('welcome_message', 'Hello', 'appearance');
    $this->agent->setSetting('text_translations', ['welcome_message' => ['de' => 'Hallo']], 'appearance');

    $settings = app(BuildWidgetSettingsAction::class)->execute($this->agent->fresh(), 'it-IT');

    expect($settings->welcome_message)->toBe('Hello');
});
