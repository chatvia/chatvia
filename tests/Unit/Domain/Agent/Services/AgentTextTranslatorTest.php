<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Services\AgentTextTranslator;
use Domain\Agent\ValueObjects\TranslatableText;
use Domain\Llm\Operations\Translate\TextTranslator;

/**
 * AgentTextTranslator sends an agent's copy to the model in the agent's
 * enabled languages, gives each text what came back, and keeps the owner's
 * manual edits.
 */
beforeEach(function (): void {
    $this->agent = createAgent(User::factory()->create());
});

it('translates only the enabled languages', function (): void {
    $this->agent->setSetting('enabled_languages', ['de', 'fr'], 'appearance');

    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()
        ->withArgs(fn (array $fields, array $languages): bool => $fields === ['k0' => 'Hello?'] && array_keys($languages) === ['de', 'fr'])
        ->andReturn(['k0' => ['de' => 'Hallo?', 'fr' => 'Salut ?']]);

    $result = app(AgentTextTranslator::class)->translate($this->agent->fresh(), ['k0' => new TranslatableText('Hello?')]);

    expect($result['k0']->translations)->toBe(['de' => 'Hallo?', 'fr' => 'Salut ?']);
});

it('gives no translations to a text the model skipped and keeps manual edits', function (): void {
    $this->agent->setSetting('enabled_languages', ['de'], 'appearance');

    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()
        ->andReturn(['k0' => ['de' => 'Hallo?']]);

    $result = app(AgentTextTranslator::class)->translate($this->agent->fresh(), [
        'k0' => new TranslatableText('Hello?'),
        'k1' => new TranslatableText('Bye?', ['de' => 'Alt'], ['de' => 'Tschüss?']),
    ]);

    expect($result['k1']->translations)->toBe([])
        ->and($result['k1']->overrides)->toBe(['de' => 'Tschüss?'])
        ->and($result['k1']->base)->toBe('Bye?');
});

it('makes no model call for no texts', function (): void {
    $this->mock(TextTranslator::class)->shouldReceive('translate')->never();

    expect(app(AgentTextTranslator::class)->translate($this->agent, []))->toBe([]);
});

it('makes no model call and changes nothing when no language can be chosen', function (): void {
    config(['chatvia.languages' => ['auto' => 'Auto']]);
    $this->mock(TextTranslator::class)->shouldReceive('translate')->never();
    $text = new TranslatableText('Hello?', ['de' => 'Hallo?']);

    expect(app(AgentTextTranslator::class)->translate($this->agent, ['k0' => $text]))->toBe(['k0' => $text]);
});
