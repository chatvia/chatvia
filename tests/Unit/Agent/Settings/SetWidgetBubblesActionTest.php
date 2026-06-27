<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Settings\SetWidgetBubblesAction;
use Domain\Llm\Operations\Translate\TextTranslator;

beforeEach(function (): void {
    $this->agent = createAgent(User::factory()->create());
    // Default: no real LLM calls. Tests that assert translations re-mock below.
    $this->mock(TextTranslator::class)->shouldReceive('translate')->andReturn([]);
});

it('stores bubbles in the structured shape and drops blanks', function (): void {
    $result = app(SetWidgetBubblesAction::class)->execute($this->agent, [
        ['text' => 'Hi there?'],
        ['text' => '   '], // blank → dropped
        ['text' => 'Need help?'],
    ]);

    expect(array_column($result, 'text'))->toBe(['Hi there?', 'Need help?'])
        ->and($result[0])->toHaveKeys(['text', 'translations', 'overrides']);

    expect(array_column($this->agent->fresh()->settings('widget')->get('bubbles'), 'text'))
        ->toBe(['Hi there?', 'Need help?']);
});

it('translates only the enabled languages', function (): void {
    $this->agent->setSetting('enabled_languages', ['de', 'fr'], 'appearance');

    $captured = null;
    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()
        ->andReturnUsing(function (array $fields, array $languages) use (&$captured): array {
            $captured = $languages;

            return ['k0' => ['de' => 'Hallo?', 'fr' => 'Salut ?']];
        });

    $result = app(SetWidgetBubblesAction::class)->execute($this->agent->fresh(), [['text' => 'Hello?']]);

    expect(array_keys($captured))->toBe(['de', 'fr'])
        ->and($result[0]['translations'])->toBe(['de' => 'Hallo?', 'fr' => 'Salut ?']);
});

it('preserves manual overrides and skips re-translation when the text is unchanged', function (): void {
    $this->agent->setSetting('enabled_languages', ['de'], 'appearance');

    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()->andReturn(['k0' => ['de' => 'Hallo?']]);

    $action = app(SetWidgetBubblesAction::class);
    $action->execute($this->agent->fresh(), [['text' => 'Hi?']]);

    // Same text + a hand-typed German override → the translator must NOT run again.
    $second = $action->execute($this->agent->fresh(), [
        ['text' => 'Hi?', 'overrides' => ['de' => 'Servus?']],
    ]);

    expect($second[0]['translations'])->toBe(['de' => 'Hallo?'])
        ->and($second[0]['overrides'])->toBe(['de' => 'Servus?']);
});

it('resolves bubbles per locale (override > translation > base) and coerces legacy strings', function (): void {
    // Legacy plain-string list still resolves to itself.
    $this->agent->setSetting('bubbles', ['Plain one', 'Plain two'], 'widget');
    expect($this->agent->fresh()->resolvedBubblesForLocale('de'))->toBe(['Plain one', 'Plain two']);

    $this->agent->setSetting('bubbles', [
        ['text' => 'Hello', 'translations' => ['de' => 'Hallo'], 'overrides' => ['de' => 'Servus']],
        ['text' => 'Bye', 'translations' => ['de' => 'Tschüss'], 'overrides' => []],
        ['text' => 'Welcome', 'translations' => [], 'overrides' => []],
    ], 'widget');

    expect($this->agent->fresh()->resolvedBubblesForLocale('de-DE'))->toBe(['Servus', 'Tschüss', 'Welcome'])
        ->and($this->agent->fresh()->resolvedBubblesForLocale('auto'))->toBe(['Hello', 'Bye', 'Welcome']);
});

it('normalizes legacy string bubbles for the editor', function (): void {
    $this->agent->setSetting('bubbles', ['One', 'Two'], 'widget');

    expect($this->agent->fresh()->normalizedBubbles())->toBe([
        ['text' => 'One', 'translations' => [], 'overrides' => []],
        ['text' => 'Two', 'translations' => [], 'overrides' => []],
    ]);
});
