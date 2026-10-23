<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Settings\SetWidgetBubblesAction;
use Domain\Agent\Data\Settings\Copy\BubbleData;
use Domain\Agent\Data\Settings\Copy\UpdateBubblesData;
use Domain\Llm\Operations\Translate\TextTranslator;

beforeEach(function (): void {
    $this->agent = createAgent(User::factory()->create());
    // Default: no real LLM calls. Tests that assert translations re-mock below.
    $this->mock(TextTranslator::class)->shouldReceive('translate')->andReturn([]);
});

it('stores bubbles in the structured shape and drops blanks', function (): void {
    $result = app(SetWidgetBubblesAction::class)->execute($this->agent, new UpdateBubblesData(bubbles: [
        new BubbleData('Hi there?'),
        new BubbleData('   '), // blank → dropped
        new BubbleData('Need help?'),
    ]));

    expect(array_column($result, 'text'))->toBe(['Hi there?', 'Need help?'])
        ->and($result[0])->toBeInstanceOf(BubbleData::class);

    expect(array_column($this->agent->fresh()->settings('widget')->get('bubbles'), 'text'))
        ->toBe(['Hi there?', 'Need help?']);
});

it('stores the AI translations the translator returns', function (): void {
    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()->andReturn(['k0' => ['de' => 'Hallo?', 'fr' => 'Salut ?']]);

    $result = app(SetWidgetBubblesAction::class)->execute($this->agent->fresh(), new UpdateBubblesData(bubbles: [new BubbleData('Hello?')]));

    expect($result[0]->translations)->toBe(['de' => 'Hallo?', 'fr' => 'Salut ?']);
});

it('preserves manual overrides and skips re-translation when the text is unchanged', function (): void {
    $this->agent->setSetting('enabled_languages', ['de'], 'appearance');

    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()->andReturn(['k0' => ['de' => 'Hallo?']]);

    $action = app(SetWidgetBubblesAction::class);
    $action->execute($this->agent->fresh(), new UpdateBubblesData(bubbles: [new BubbleData('Hi?')]));

    // Same text + a hand-typed German override → the translator must NOT run again.
    $second = $action->execute($this->agent->fresh(), new UpdateBubblesData(bubbles: [
        new BubbleData('Hi?', overrides: ['de' => 'Servus?']),
    ]));

    expect($second[0]->translations)->toBe(['de' => 'Hallo?'])
        ->and($second[0]->overrides)->toBe(['de' => 'Servus?']);
});

it('resolves bubbles per locale (override > translation > base)', function (): void {
    $this->agent->setSetting('bubbles', [
        ['text' => 'Hello', 'translations' => ['de' => 'Hallo'], 'overrides' => ['de' => 'Servus']],
        ['text' => 'Bye', 'translations' => ['de' => 'Tschüss'], 'overrides' => []],
        ['text' => 'Welcome', 'translations' => [], 'overrides' => []],
    ], 'widget');

    expect($this->agent->fresh()->resolvedBubblesForLocale('de-DE'))->toBe(['Servus', 'Tschüss', 'Welcome'])
        ->and($this->agent->fresh()->resolvedBubblesForLocale('auto'))->toBe(['Hello', 'Bye', 'Welcome']);
});

it('gives the editor every bubble in its full shape', function (): void {
    $this->agent->setSetting('bubbles', [
        ['text' => 'One', 'translations' => ['de' => 'Eins'], 'overrides' => []],
        ['text' => 'Two', 'translations' => [], 'overrides' => ['de' => 'Zwo']],
    ], 'widget');

    expect($this->agent->fresh()->normalizedBubbles())->toBe([
        ['text' => 'One', 'translations' => ['de' => 'Eins'], 'overrides' => []],
        ['text' => 'Two', 'translations' => [], 'overrides' => ['de' => 'Zwo']],
    ]);
});
