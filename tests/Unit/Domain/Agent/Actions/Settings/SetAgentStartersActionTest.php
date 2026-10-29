<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Settings\SetAgentStartersAction;
use Domain\Agent\Data\Settings\Copy\StarterData;
use Domain\Agent\Data\Settings\Copy\UpdateStartersData;
use Domain\Agent\Models\AgentStarter;
use Domain\Llm\Operations\Translate\TextTranslator;

/**
 * Saving an agent's conversation starters replaces the one ordered list the
 * agent keeps, gives each starter its own id and drops blank ones.
 */
it('replaces the starters with one ordered list, an id per starter and no blanks', function (): void {
    $agent = createAgent(User::factory()->create());
    $this->mock(TextTranslator::class)->shouldReceive('translate')->andReturn([]);
    $action = app(SetAgentStartersAction::class);
    $action->execute($agent, new UpdateStartersData(starters: [new StarterData(null, 'old')]));

    $result = $action->execute($agent->fresh(), new UpdateStartersData(starters: [
        3 => new StarterData(null, 'b', 'BoltIcon'),
        5 => new StarterData(null, '   '),
        7 => new StarterData(null, 'a'),
    ]));

    expect($result->pluck('text')->all())->toBe(['b', 'a'])
        ->and($result->pluck('icon')->all())->toBe(['BoltIcon', null])
        ->and($result[0]->id)->not->toBeEmpty()
        ->and($result[0]->id)->not->toBe($result[1]->id)
        ->and(AgentStarter::query()->where('agent_id', $agent->id)->count())->toBe(1)
        ->and(array_column($agent->fresh()->starterSet->starters, 'text'))->toBe(['b', 'a']);
});
