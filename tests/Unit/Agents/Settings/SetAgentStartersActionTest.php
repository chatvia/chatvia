<?php

use App\Auth\Models\User;
use Domain\Agents\Actions\Settings\SetAgentStartersAction;
use Domain\Agents\Models\AgentStarter;

it('writes the agent starters as an ordered array', function (): void {
    $agent = createAgent(User::factory()->create());

    app(SetAgentStartersAction::class)->execute($agent, ['First?', 'Second?']);

    expect($agent->starterSet()->first()->text)->toBe(['First?', 'Second?']);
});

it('replaces existing starters and keeps a single row', function (): void {
    $agent = createAgent(User::factory()->create());
    $action = app(SetAgentStartersAction::class);

    $action->execute($agent, ['old']);
    $action->execute($agent, ['new-a', 'new-b']);

    expect(AgentStarter::where('agent_id', $agent->id)->count())->toBe(1)
        ->and($agent->starterSet()->first()->text)->toBe(['new-a', 'new-b']);
});

it('reindexes sparse keys into a clean list', function (): void {
    $agent = createAgent(User::factory()->create());

    app(SetAgentStartersAction::class)->execute($agent, [3 => 'b', 7 => 'a']);

    expect($agent->starterSet()->first()->text)->toBe(['b', 'a']);
});
