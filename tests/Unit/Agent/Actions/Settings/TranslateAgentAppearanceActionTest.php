<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Settings\TranslateAgentAppearanceAction;
use Domain\Agent\Data\Settings\AppearanceTextData;
use Domain\Llm\Operations\Translate\TextTranslator;

/**
 * TranslateAgentAppearanceAction persists the three base appearance strings and
 * (re)generates their AI translations, while leaving the owner's manual
 * per-language overrides untouched.
 */
beforeEach(function (): void {
    $this->agent = createAgent(User::factory()->create());
});

it('persists the base strings and stores the AI translations', function (): void {
    $map = [
        'chat_window_title' => ['de' => 'Chat', 'fr' => 'Discussion'],
        'welcome_message' => ['de' => 'Wie kann ich helfen?', 'fr' => 'Comment puis-je aider ?'],
        'composer_placeholder' => ['de' => 'Frag mich etwas …', 'fr' => 'Demandez …'],
    ];

    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()->andReturn($map);

    $result = app(TranslateAgentAppearanceAction::class)->execute($this->agent, new AppearanceTextData(
        welcome_message: 'How can I help?',
        chat_window_title: 'Chat',
        composer_placeholder: 'Ask me anything…',
    ));

    $appearance = $this->agent->fresh()->settings('appearance');

    expect($result)->toBe($map)
        ->and($appearance->get('welcome_message'))->toBe('How can I help?')
        ->and($appearance->get('chat_window_title'))->toBe('Chat')
        ->and($appearance->get('composer_placeholder'))->toBe('Ask me anything…')
        ->and($appearance->get('text_translations'))->toBe($map);
});

it('skips the LLM call when the base text is unchanged and every enabled language is translated', function (): void {
    $this->agent->setSetting('enabled_languages', ['de'], 'appearance');
    $this->agent->setSetting('chat_window_title', 'Chat', 'appearance');
    $this->agent->setSetting('welcome_message', 'How can I help?', 'appearance');
    $this->agent->setSetting('composer_placeholder', 'Ask me anything…', 'appearance');
    $existing = [
        'chat_window_title' => ['de' => 'Chat'],
        'welcome_message' => ['de' => 'Wie kann ich helfen?'],
        'composer_placeholder' => ['de' => 'Frag mich etwas …'],
    ];
    $this->agent->setSetting('text_translations', $existing, 'appearance');

    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->never();

    $result = app(TranslateAgentAppearanceAction::class)->execute($this->agent->fresh(), new AppearanceTextData(
        welcome_message: 'How can I help?',
        chat_window_title: 'Chat',
        composer_placeholder: 'Ask me anything…',
    ));

    expect($result)->toBe($existing);
});

it('translates only the enabled languages', function (): void {
    $this->agent->setSetting('enabled_languages', ['de', 'fr'], 'appearance');

    $captured = null;
    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()
        ->andReturnUsing(function (array $fields, array $languages) use (&$captured): array {
            $captured = $languages;

            return ['welcome_message' => ['de' => 'Hallo', 'fr' => 'Bonjour']];
        });

    app(TranslateAgentAppearanceAction::class)->execute($this->agent->fresh(), new AppearanceTextData(
        welcome_message: 'Hello',
        chat_window_title: 'Chat',
        composer_placeholder: 'Ask…',
    ));

    expect(array_keys($captured))->toBe(['de', 'fr']);
});

it('re-translates when the owner enables a language the cached set does not cover', function (): void {
    $this->agent->setSetting('enabled_languages', ['de', 'fr'], 'appearance');
    $this->agent->setSetting('chat_window_title', 'Chat', 'appearance');
    $this->agent->setSetting('welcome_message', 'Hello', 'appearance');
    $this->agent->setSetting('composer_placeholder', 'Ask…', 'appearance');
    // Only German cached; French is newly enabled and missing.
    $this->agent->setSetting('text_translations', [
        'chat_window_title' => ['de' => 'Chat'],
        'welcome_message' => ['de' => 'Hallo'],
        'composer_placeholder' => ['de' => 'Frag…'],
    ], 'appearance');

    // The mock's ->once() enforces that an unchanged base text still re-translates.
    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()
        ->andReturn(['welcome_message' => ['de' => 'Hallo', 'fr' => 'Bonjour']]);

    app(TranslateAgentAppearanceAction::class)->execute($this->agent->fresh(), new AppearanceTextData(
        welcome_message: 'Hello',
        chat_window_title: 'Chat',
        composer_placeholder: 'Ask…',
    ));
});

it('regenerates translations when the base text changes and leaves manual overrides intact', function (): void {
    $this->agent->setSetting('text_overrides', ['welcome_message' => ['de' => 'Servus!']], 'appearance');
    $this->agent->setSetting('text_translations', ['welcome_message' => ['de' => 'Hallo']], 'appearance');

    $fresh = ['welcome_message' => ['de' => 'Wie kann ich Ihnen heute helfen?']];

    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()->andReturn($fresh);

    app(TranslateAgentAppearanceAction::class)->execute($this->agent->fresh(), new AppearanceTextData(
        welcome_message: 'How can I help you today?',
        chat_window_title: 'Chat',
        composer_placeholder: 'Ask…',
    ));

    $appearance = $this->agent->fresh()->settings('appearance');

    expect($appearance->get('text_translations'))->toBe($fresh)
        ->and($appearance->get('text_overrides'))->toBe(['welcome_message' => ['de' => 'Servus!']]);
});
