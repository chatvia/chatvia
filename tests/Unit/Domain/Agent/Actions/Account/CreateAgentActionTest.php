<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Account\CreateAgentAction;
use Domain\Agent\Data\Account\CreateAgentData;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;

/**
 * CreateAgentAction keys a new agent to the workspace (also when a teammate
 * creates it), makes it protected with a generated identity, and attaches the
 * topics the workspace can use, never another workspace's custom topics.
 */
it('creates a protected workspace agent with the workspace topics only', function (): void {
    $owner = User::factory()->create();
    $team = app(CreateDefaultTeamAction::class)->execute($owner);
    $employee = User::factory()->employee($owner)->create();
    $system = Topic::factory()->create(['workspace_id' => null, 'topic' => 'Billing']);
    $own = Topic::factory()->create(['workspace_id' => $owner->workspace()->id]);
    $foreign = Topic::factory()->create(['workspace_id' => User::factory()->create()->workspace()->id]);

    $agent = app(CreateAgentAction::class)->execute(new CreateAgentData(
        user: $employee,
        team: $team,
        name: 'Support Bot',
    ));

    $attached = $agent->topics()->pluck('topics.id');

    expect($agent->workspace_id)->toBe($owner->workspace()->id)
        ->and($agent->team_id)->toBe($team->id)
        ->and($agent->is_public)->toBeFalse()
        ->and($agent->slug)->toBe($agent->external_id)
        ->and($agent->auth_secret)->toStartWith('cvsk_')
        ->and($attached)->toContain($system->id)
        ->and($attached)->toContain($own->id)
        ->and($attached)->not->toContain($foreign->id)
        ->and($attached->count())->toBe(Topic::forWorkspace($owner->workspace())->count());
});
