<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Settings\SetAgentStartersAction;
use Domain\Agent\Actions\Settings\SetWidgetBubblesAction;
use Domain\Agent\Data\Settings\Copy\BubbleData;
use Domain\Agent\Data\Settings\Copy\StarterData;
use Domain\Agent\Data\Settings\Copy\UpdateBubblesData;
use Domain\Agent\Data\Settings\Copy\UpdateStartersData;
use Domain\Agent\Models\Agent;
use Domain\Llm\Operations\Translate\TextTranslator;

/**
 * Conversation starters and widget bubbles are translated the same way when
 * saved: the translator's output is stored per language, and a text that is
 * unchanged and already translated into every enabled language keeps its
 * translations (and the owner's own edits) without a new translator call.
 */
beforeEach(function (): void {
    $this->agent = createAgent(User::factory()->create());
});

/**
 * Each copy list, saved with one text: an action that saves it and returns the
 * saved items. A starter keeps its id across saves, so the second save names
 * the id the first one returned.
 */
dataset('translated copy', [
    'starters' => [fn (Agent $agent, string $text, array $overrides = [], ?StarterData $saved = null): array => app(SetAgentStartersAction::class)->execute($agent, new UpdateStartersData(starters: [
        new StarterData($saved?->id, $text, null, overrides: $overrides),
    ]))],
    'bubbles' => [fn (Agent $agent, string $text, array $overrides = []): array => app(SetWidgetBubblesAction::class)->execute($agent, new UpdateBubblesData(bubbles: [
        new BubbleData($text, overrides: $overrides),
    ]))],
]);

it('stores the AI translations the translator returns', function (Closure $save): void {
    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()->andReturn(['k0' => ['de' => 'Hallo?', 'fr' => 'Salut ?']]);

    $saved = $save($this->agent->fresh(), 'Hello?');

    expect($saved[0]->translations)->toBe(['de' => 'Hallo?', 'fr' => 'Salut ?']);
})->with('translated copy');

it('keeps the translations and the owner\'s edits, without translating again, while the text is unchanged', function (Closure $save): void {
    // One language, so the stored translation covers every enabled one.
    $this->agent->setSetting('enabled_languages', ['de'], 'appearance');
    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()->andReturn(['k0' => ['de' => 'Hallo?']]);

    $first = $save($this->agent->fresh(), 'Hi?');
    $second = $save($this->agent->fresh(), 'Hi?', ['de' => 'Servus?'], $first[0]);

    expect($second[0]->translations)->toBe(['de' => 'Hallo?'])
        ->and($second[0]->overrides)->toBe(['de' => 'Servus?']);
})->with('translated copy');
