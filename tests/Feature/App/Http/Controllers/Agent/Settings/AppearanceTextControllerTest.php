<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Operations\Translate\TextTranslator;
use Mockery\MockInterface;

/**
 * Saving the appearance texts in Setup stores them and answers with their AI
 * translations into every supported language. How the chat surfaces
 * pick a language is in tests/Unit/Domain/Agent/Services/AppearanceTextResolverTest.php.
 */
it('saves the base text and returns the AI translations', function (): void {
    $agent = Agent::factory()->create();
    $map = [
        'chat_window_title' => ['de' => 'Chat'],
        'welcome_message' => ['de' => 'Hallo, wie kann ich helfen?'],
        'composer_placeholder' => ['de' => 'Frag mich etwas …'],
    ];
    $this->mock(TextTranslator::class, fn (MockInterface $mock): mixed => $mock->shouldReceive('translate')->once()->andReturn($map));

    $this->actingAs($agent->workspace->owner)
        ->postJson(route('agents.appearance.text', $agent->external_id), [
            'chat_window_title' => 'Chat',
            'welcome_message' => 'Hello, how can I help?',
            'composer_placeholder' => 'Ask anything…',
        ])
        ->assertOk()
        ->assertJsonPath('translations.welcome_message.de', 'Hallo, wie kann ich helfen?');

    $appearance = $agent->fresh()->settings('appearance');

    expect($appearance->get('welcome_message'))->toBe('Hello, how can I help?')
        ->and($appearance->get('text_translations'))->toBe($map);
});
