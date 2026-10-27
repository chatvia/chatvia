<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Account\CreateAgentAction;
use Domain\Agent\Data\Account\CreateAgentData;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;

/**
 * CreateAgentAction wraps the agent insert + the topic attach in one
 * transaction and stamps the generated identity (external_id doubles as the
 * initial slug, auth_secret gets the cvsk_ prefix). Agents are born
 * protected (is_public = false) — the messaging-channel surface relies on
 * that default.
 */
it('creates a protected agent with generated identity and attaches the workspace topics', function (): void {
    $owner = User::factory()->create();
    $team = app(CreateDefaultTeamAction::class)->execute($owner);

    // System topics belong to no workspace.
    $topicA = Topic::factory()->create(['workspace_id' => null, 'topic' => 'Billing']);
    $topicB = Topic::factory()->create(['workspace_id' => null, 'topic' => 'Support']);

    $agent = app(CreateAgentAction::class)->execute(new CreateAgentData(
        user: $owner,
        team: $team,
        name: 'Support Bot',
    ));

    expect($agent->exists)->toBeTrue()
        ->and($agent->name)->toBe('Support Bot')
        ->and($agent->workspace_id)->toBe($owner->workspace()->id)
        ->and($agent->team_id)->toBe($team->id)
        // Agents default to protected; channels enforce nothing downstream.
        ->and($agent->is_public)->toBeFalse()
        ->and($agent->external_id)->not->toBeEmpty()
        // The generated external id doubles as the initial slug.
        ->and($agent->slug)->toBe($agent->external_id)
        ->and($agent->auth_secret)->toStartWith('cvsk_')
        ->and(strlen((string) $agent->auth_secret))->toBe(61);

    // Every topic the workspace can use (system topics included) is attached.
    $attachedIds = $agent->topics()->pluck('topics.id');

    expect($attachedIds)->toContain($topicA->id)
        ->and($attachedIds)->toContain($topicB->id)
        ->and($attachedIds->count())->toBe(Topic::forWorkspace($owner->workspace())->count());
});

it('keys the agent to the workspace when a teammate creates it', function (): void {
    $owner = User::factory()->create();
    $team = app(CreateDefaultTeamAction::class)->execute($owner);
    $employee = User::factory()->employee($owner)->create();

    $agent = app(CreateAgentAction::class)->execute(new CreateAgentData(
        user: $employee,
        team: $team,
        name: 'Team Bot',
    ));

    // agent.workspace_id keys every account-level stat (dashboard, analytics,
    // message limit), so it must resolve to the owner's workspace, also when a
    // teammate creates the agent.
    expect($agent->workspace_id)->toBe($owner->workspace()->id);
});

it('generates a distinct identity per agent', function (): void {
    $owner = User::factory()->create();
    $team = app(CreateDefaultTeamAction::class)->execute($owner);

    $first = app(CreateAgentAction::class)->execute(new CreateAgentData(
        user: $owner,
        team: $team,
        name: 'First',
    ));
    $second = app(CreateAgentAction::class)->execute(new CreateAgentData(
        user: $owner,
        team: $team,
        name: 'Second',
    ));

    expect($first->external_id)->not->toBe($second->external_id)
        ->and($first->auth_secret)->not->toBe($second->auth_secret);
});

it('attaches the workspace own topics but never another workspace custom topics', function (): void {
    $owner = User::factory()->create();
    $team = app(CreateDefaultTeamAction::class)->execute($owner);
    $stranger = User::factory()->create();

    $own = Topic::factory()->create(['workspace_id' => $owner->workspace()->id]);
    $foreign = Topic::factory()->create(['workspace_id' => $stranger->workspace()->id]);

    $agent = app(CreateAgentAction::class)->execute(new CreateAgentData(
        user: $owner,
        team: $team,
        name: 'Support Bot',
    ));

    $attached = $agent->topics()->pluck('topics.id');

    expect($attached)->toContain($own->id)
        ->and($attached)->not->toContain($foreign->id);
});
