<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Settings\SetAgentStartersAction;
use Domain\Agent\Models\AgentStarter;

// Starters are compared with toEqual (==), not toBe (===): the jsonb column
// canonicalizes key order inside each starter object, so 'icon' can come back
// before 'text'. Item order (the list itself) is still asserted.

it('writes the agent starters as an ordered array', function (): void {
    $agent = createAgent(User::factory()->create());

    app(SetAgentStartersAction::class)->execute($agent, [
        ['text' => 'First?', 'icon' => 'SparklesIcon'],
        ['text' => 'Second?', 'icon' => null],
    ]);

    expect($agent->starterSet()->first()->starters)->toEqual([
        ['text' => 'First?', 'icon' => 'SparklesIcon'],
        ['text' => 'Second?', 'icon' => null],
    ]);
});

it('replaces existing starters and keeps a single row', function (): void {
    $agent = createAgent(User::factory()->create());
    $action = app(SetAgentStartersAction::class);

    $action->execute($agent, [['text' => 'old', 'icon' => null]]);
    $action->execute($agent, [
        ['text' => 'new-a', 'icon' => null],
        ['text' => 'new-b', 'icon' => 'BoltIcon'],
    ]);

    expect(AgentStarter::where('agent_id', $agent->id)->count())->toBe(1)
        ->and($agent->starterSet()->first()->starters)->toEqual([
            ['text' => 'new-a', 'icon' => null],
            ['text' => 'new-b', 'icon' => 'BoltIcon'],
        ]);
});

it('reindexes sparse keys and defaults a missing icon to null', function (): void {
    $agent = createAgent(User::factory()->create());

    app(SetAgentStartersAction::class)->execute($agent, [
        3 => ['text' => 'b'],
        7 => ['text' => 'a'],
    ]);

    expect($agent->starterSet()->first()->starters)->toEqual([
        ['text' => 'b', 'icon' => null],
        ['text' => 'a', 'icon' => null],
    ]);
});
