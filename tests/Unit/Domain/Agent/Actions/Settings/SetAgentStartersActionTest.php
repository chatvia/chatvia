<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Settings\SetAgentStartersAction;
use Domain\Agent\Data\Settings\Copy\StarterData;
use Domain\Agent\Data\Settings\Copy\UpdateStartersData;
use Domain\Agent\Models\AgentStarter;
use Domain\Llm\Operations\Translate\TextTranslator;

/**
 * Saving an agent's conversation starters keeps one ordered list, gives each
 * starter an id and drops blank ones. How their translations are made and
 * kept is in StartersAndBubblesTranslationTest.
 */
beforeEach(function (): void {
    $this->agent = createAgent(User::factory()->create());
    // Default: no real LLM calls. Tests that assert translations re-mock below.
    $this->mock(TextTranslator::class)->shouldReceive('translate')->andReturn([]);
});

it('writes the agent starters as an ordered list and assigns each an id', function (): void {
    $result = app(SetAgentStartersAction::class)->execute($this->agent, new UpdateStartersData(starters: [
        new StarterData(null, 'First?', 'SparklesIcon'),
        new StarterData(null, 'Second?'),
    ]));

    expect($result->pluck('text')->all())->toBe(['First?', 'Second?'])
        ->and($result->pluck('icon')->all())->toBe(['SparklesIcon', null])
        ->and($result[0]->id)->not->toBeEmpty()
        ->and($result[1]->id)->not->toBeEmpty()
        ->and($result[0]->id)->not->toBe($result[1]->id);

    expect(array_column($this->agent->starterSet()->first()->starters, 'text'))->toBe(['First?', 'Second?']);
});

it('replaces existing starters and keeps a single row', function (): void {
    $action = app(SetAgentStartersAction::class);

    $action->execute($this->agent, new UpdateStartersData(starters: [new StarterData(null, 'old')]));
    $action->execute($this->agent->fresh(), new UpdateStartersData(starters: [
        new StarterData(null, 'new-a'),
        new StarterData(null, 'new-b', 'BoltIcon'),
    ]));

    expect(AgentStarter::where('agent_id', $this->agent->id)->count())->toBe(1)
        ->and(array_column($this->agent->fresh()->starterSet->starters, 'text'))->toBe(['new-a', 'new-b']);
});

it('reindexes sparse keys, drops blanks, and defaults a missing icon to null', function (): void {
    $result = app(SetAgentStartersAction::class)->execute($this->agent, new UpdateStartersData(starters: [
        3 => new StarterData(null, 'b'),
        5 => new StarterData(null, '   '), // blank → dropped
        7 => new StarterData(null, 'a'),
    ]));

    expect($result->pluck('text')->all())->toBe(['b', 'a'])
        ->and($result[0]->icon)->toBeNull();
});
