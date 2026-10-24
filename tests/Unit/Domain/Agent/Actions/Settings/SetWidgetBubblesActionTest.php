<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Settings\SetWidgetBubblesAction;
use Domain\Agent\Data\Settings\Copy\BubbleData;
use Domain\Agent\Data\Settings\Copy\UpdateBubblesData;
use Domain\Llm\Operations\Translate\TextTranslator;

/**
 * Saving the widget's chat bubbles stores them in their structured shape and
 * drops blank ones. How their translations are made and kept is in
 * StartersAndBubblesTranslationTest.
 */
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
