<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Settings\SetAgentStartersAction;
use Domain\Agent\Data\Settings\Copy\StarterData;
use Domain\Agent\Models\AgentStarter;
use Domain\Llm\Operations\Translate\TextTranslator;

beforeEach(function (): void {
    $this->agent = createAgent(User::factory()->create());
    // Default: no real LLM calls. Tests that assert translations re-mock below.
    $this->mock(TextTranslator::class)->shouldReceive('translate')->andReturn([]);
});

it('writes the agent starters as an ordered list and assigns each an id', function (): void {
    $result = app(SetAgentStartersAction::class)->execute($this->agent, [
        new StarterData(null, 'First?', 'SparklesIcon'),
        new StarterData(null, 'Second?'),
    ]);

    expect(array_column($result, 'text'))->toBe(['First?', 'Second?'])
        ->and(array_column($result, 'icon'))->toBe(['SparklesIcon', null])
        ->and($result[0]->id)->not->toBeEmpty()
        ->and($result[1]->id)->not->toBeEmpty()
        ->and($result[0]->id)->not->toBe($result[1]->id);

    expect(array_column($this->agent->starterSet()->first()->starters, 'text'))->toBe(['First?', 'Second?']);
});

it('replaces existing starters and keeps a single row', function (): void {
    $action = app(SetAgentStartersAction::class);

    $action->execute($this->agent, [new StarterData(null, 'old')]);
    $action->execute($this->agent->fresh(), [
        new StarterData(null, 'new-a'),
        new StarterData(null, 'new-b', 'BoltIcon'),
    ]);

    expect(AgentStarter::where('agent_id', $this->agent->id)->count())->toBe(1)
        ->and(array_column($this->agent->fresh()->starterSet->starters, 'text'))->toBe(['new-a', 'new-b']);
});

it('reindexes sparse keys, drops blanks, and defaults a missing icon to null', function (): void {
    $result = app(SetAgentStartersAction::class)->execute($this->agent, [
        3 => new StarterData(null, 'b'),
        5 => new StarterData(null, '   '), // blank → dropped
        7 => new StarterData(null, 'a'),
    ]);

    expect(array_column($result, 'text'))->toBe(['b', 'a'])
        ->and($result[0]->icon)->toBeNull();
});

it('stores the AI translations the translator returns', function (): void {
    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()->andReturn(['k0' => ['de' => 'Hallo?', 'fr' => 'Salut ?']]);

    $result = app(SetAgentStartersAction::class)->execute($this->agent, [
        new StarterData(null, 'Hello?'),
    ]);

    expect($result[0]->translations)->toBe(['de' => 'Hallo?', 'fr' => 'Salut ?']);
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

    app(SetAgentStartersAction::class)->execute($this->agent->fresh(), [
        new StarterData(null, 'Hello?'),
    ]);

    expect(array_keys($captured))->toBe(['de', 'fr']);
});

it('preserves manual overrides and skips re-translation when the text is unchanged', function (): void {
    // Scope to one language so the cached translation fully covers the enabled set.
    $this->agent->setSetting('enabled_languages', ['de'], 'appearance');

    $this->mock(TextTranslator::class)
        ->shouldReceive('translate')->once()->andReturn(['k0' => ['de' => 'Hallo?']]);

    $action = app(SetAgentStartersAction::class);

    $first = $action->execute($this->agent->fresh(), [new StarterData(null, 'Hi?')]);
    $id = $first[0]->id;

    // Same text + a hand-typed German override → the translator must NOT run again.
    $second = $action->execute($this->agent->fresh(), [
        new StarterData($id, 'Hi?', null, overrides: ['de' => 'Servus?']),
    ]);

    expect($second[0]->translations)->toBe(['de' => 'Hallo?'])
        ->and($second[0]->overrides)->toBe(['de' => 'Servus?']);
});
