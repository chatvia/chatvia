<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Account\CreateAgentAction;
use Domain\Agent\Data\Account\CreateAgentData;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Membership\Team\Services\TeamService;

/**
 * CreateAgentAction wraps the agent insert + the topic attach in one
 * transaction and stamps the generated identity (external_id doubles as the
 * initial sub_domain, auth_secret gets the cvsk_ prefix). Agents are born
 * protected (is_public = false) — the messaging-channel surface relies on
 * that default.
 */
it('creates a protected agent with generated identity and attaches every topic', function (): void {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createDefaultTeam($owner);

    $topicA = Topic::create(['topic' => 'Billing', 'description' => 'Billing questions']);
    $topicB = Topic::create(['topic' => 'Support', 'description' => 'Support questions']);

    $agent = app(CreateAgentAction::class)->execute(new CreateAgentData(
        user: $owner,
        team: $team,
        name: 'Support Bot',
    ));

    expect($agent->exists)->toBeTrue()
        ->and($agent->name)->toBe('Support Bot')
        ->and($agent->user_id)->toBe($owner->id)
        ->and($agent->team_id)->toBe($team->id)
        // Agents default to protected; channels enforce nothing downstream.
        ->and($agent->is_public)->toBeFalse()
        ->and($agent->external_id)->not->toBeEmpty()
        // The generated external id doubles as the initial sub_domain.
        ->and($agent->sub_domain)->toBe($agent->external_id)
        ->and($agent->auth_secret)->toStartWith('cvsk_')
        ->and(strlen((string) $agent->auth_secret))->toBe(61);

    // Every topic in the catalog (seeded system topics included) is attached.
    $attachedIds = $agent->topics()->pluck('topics.id');

    expect($attachedIds)->toContain($topicA->id)
        ->and($attachedIds)->toContain($topicB->id)
        ->and($attachedIds->count())->toBe(Topic::count());
});

it('stamps the workspace owner as agent owner when an employee creates the agent', function (): void {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createDefaultTeam($owner);
    $employee = User::factory()->employee($owner)->create();

    $agent = app(CreateAgentAction::class)->execute(new CreateAgentData(
        user: $employee,
        team: $team,
        name: 'Team Bot',
    ));

    // agent.user_id keys every account-level stat (dashboard, analytics,
    // quota), so it must resolve to the workspace owner, never the employee.
    expect($agent->user_id)->toBe($owner->id)
        ->and($agent->user_id)->not->toBe($employee->id);
});

it('generates a distinct identity per agent', function (): void {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createDefaultTeam($owner);

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
