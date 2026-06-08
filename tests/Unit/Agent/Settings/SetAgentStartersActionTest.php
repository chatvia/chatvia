<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Settings\SetAgentStartersAction;
use Domain\Agent\Models\AgentStarter;

it('writes the agent starters as an ordered array', function (): void {
    $agent = createAgent(User::factory()->create());

    app(SetAgentStartersAction::class)->execute($agent, [
        ['text' => 'First?', 'icon' => 'SparklesIcon'],
        ['text' => 'Second?', 'icon' => null],
    ]);

    expect($agent->starterSet()->first()->starters)->toBe([
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
        ->and($agent->starterSet()->first()->starters)->toBe([
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

    expect($agent->starterSet()->first()->starters)->toBe([
        ['text' => 'b', 'icon' => null],
        ['text' => 'a', 'icon' => null],
    ]);
});
