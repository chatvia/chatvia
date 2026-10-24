<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Operations\Translate\TextTranslator;
use Illuminate\Support\Str;
use Mockery\MockInterface;

/**
 * The Setup appearance-text endpoints: saving auto-translates the three strings
 * into every supported language and manual overrides persist separately. How
 * the chat surfaces pick a language is in
 * tests/Unit/Domain/Agent/Services/AppearanceTextResolverTest.php.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->owner = $this->agent->workspace->owner;
});

it('saves the base text and returns the AI translations', function (): void {
    $map = [
        'chat_window_title' => ['de' => 'Chat'],
        'welcome_message' => ['de' => 'Hallo, wie kann ich helfen?'],
        'composer_placeholder' => ['de' => 'Frag mich etwas …'],
    ];

    $this->mock(TextTranslator::class, fn (MockInterface $mock): mixed => $mock->shouldReceive('translate')->once()->andReturn($map));

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
                'welcome_message' => ['de' => Str::repeat('x', 501)],
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
