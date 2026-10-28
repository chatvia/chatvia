<?php

use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Dashboard\DashboardStatsData;
use Domain\Analytics\Queries\Dashboard\DashboardStatsQuery;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DashboardStatsQuery aggregates the whole dashboard payload from the
 * analytics rollup + events stream, keyed on the viewer's workspace: only the
 * workspace's agents and rollups count, active sessions are the distinct
 * sessions that sent a message in the last 24 hours, and the messages panel
 * reads the workspace's monthly message limit when it has one.
 */
beforeEach(function (): void {
    // Mid-month, mid-day: keeps the trend (month-bucketed) and the daily
    // volume windows deterministic.
    $this->travelTo(Carbon::parse('2026-06-15 12:00:00', 'UTC'));
});

function gdsEvent(Agent $agent, string $sessionExternalId, Carbon $occurredAt): void
{
    DB::connection(config('chatvia.analytics.connection'))
        ->table('analytics_events')
        ->insert([
            'agent_id' => $agent->id,
            'workspace_id' => $agent->workspace_id,
            'session_external_id' => $sessionExternalId,
            'message_external_id' => (string) Str::uuid7(),
            'occurred_at' => $occurredAt,
        ]);
}

/**
 * The stats of a workspace with two agents and some traffic, next to another
 * workspace's traffic that must not leak in: 5 conversations and 9 replies
 * this month, sessions A (twice) and B in the last day plus a stale one, and a
 * monthly limit of 1000 with 11 messages counted. The limit's counter (11)
 * differs from the rollup sum (9) on purpose, as when 2 replies were eval
 * cases, which count but never reach the rollup.
 */
function gdsWorkspaceStats(): DashboardStatsData
{
    $owner = User::factory()->create();
    $team = app(CreateDefaultTeamAction::class)->execute($owner);
    MessageLimit::factory()->forWorkspace($owner->workspace())->create(['monthly_limit' => 1000, 'messages_used' => 11]);

    $agentA = createAgent($owner, ['name' => 'Agent A']);
    $agentB = createAgent($owner, ['name' => 'Agent B']);
    analyticsRollup($agentA, '2026-06-15 10:00:00+00', ['conversations_started' => 3, 'assistant_messages' => 5]);
    analyticsRollup($agentB, '2026-06-14 22:00:00+00', ['conversations_started' => 2, 'assistant_messages' => 4]);

    $foreignAgent = Agent::factory()->create();
    analyticsRollup($foreignAgent, '2026-06-15 10:00:00+00', ['conversations_started' => 7, 'assistant_messages' => 9]);

    $sessionA = (string) Str::uuid7();
    gdsEvent($agentA, $sessionA, now()->subHours(2));
    gdsEvent($agentA, $sessionA, now()->subHours(1));
    gdsEvent($agentB, (string) Str::uuid7(), now()->subHours(3));
    gdsEvent($agentA, (string) Str::uuid7(), now()->subDays(2));
    gdsEvent($foreignAgent, (string) Str::uuid7(), now()->subHours(1));

    return app(DashboardStatsQuery::class)->get($owner, $team);
}

it('counts the workspace\'s own agents, conversations and active sessions', function (): void {
    $stats = gdsWorkspaceStats()->toArray();

    expect($stats)->not->toHaveKey('usage')
        ->and($stats['agents']['total'])->toBe(2)
        ->and($stats['conversations'])->toBe(5)
        ->and($stats['active_sessions'])->toBe(2)
        ->and($stats['team']['members'])->toBe(0);
});

it('reads the messages used from the monthly limit\'s counter', function (): void {
    $messages = gdsWorkspaceStats()->toArray()['messages'];

    expect($messages['total'])->toBe(11)
        ->and($messages['limit'])->toBe(1000)
        ->and($messages['available'])->toBe(989);
});

it('compares this month\'s replies with last month\'s', function (): void {
    expect(gdsWorkspaceStats()->toArray()['messages']['trend'])
        ->toBe(['this_month' => 9, 'last_month' => 0, 'percent_change' => 100]);
});

it('charts the replies of the last 7 and 30 days, every day present', function (): void {
    $stats = gdsWorkspaceStats()->toArray();

    expect($stats['weekly_usage'])->toHaveCount(7)
        ->and(array_sum(array_column($stats['weekly_usage'], 'value')))->toBe(9)
        ->and($stats['monthly_usage'])->toHaveCount(30)
        ->and(array_sum(array_column($stats['monthly_usage'], 'value')))->toBe(9);
});

it('counts no knowledge sources while no agent has one', function (): void {
    expect(gdsWorkspaceStats()->toArray()['knowledge_sources'])->toBe([
        'files' => 0,
        'websites' => 0,
        'crawled_pages' => 0,
        'total' => 0,
    ]);
});

it('returns a zeroed trend and member count when no team is passed', function (): void {
    $owner = User::factory()->create();

    $stats = app(DashboardStatsQuery::class)->get($owner, null)->toArray();

    expect($stats['messages']['trend'])->toBe(['this_month' => 0, 'last_month' => 0, 'percent_change' => 0])
        ->and($stats['team']['members'])->toBe(0)
        ->and($stats['agents']['total'])->toBe(0)
        ->and($stats['conversations'])->toBe(0)
        // No limit: the panel reports this month's replies and no limit.
        ->and($stats['messages']['total'])->toBe(0)
        ->and($stats['messages']['limit'])->toBeNull()
        ->and($stats['messages']['available'])->toBeNull();
});

it('counts this month\'s replies from the rollup when the workspace has no limit', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);

    analyticsRollup($agent, '2026-06-10 10:00:00+00', ['assistant_messages' => 4]);
    // Last month does not count.
    analyticsRollup($agent, '2026-05-20 10:00:00+00', ['assistant_messages' => 6]);

    $stats = app(DashboardStatsQuery::class)->get($owner, null)->toArray();

    expect($stats['messages']['total'])->toBe(4)
        ->and($stats['messages']['limit'])->toBeNull();
});

it('counts only the sessions that sent a message as active', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);

    gdsEvent($agent, (string) Str::uuid7(), now()->subHour());
    // A thumbs reaction in the last day is no visit of its own.
    DB::connection(config('chatvia.analytics.connection'))->table('analytics_message_feedback')->insert([
        'message_external_id' => (string) Str::uuid7(),
        'agent_id' => $agent->id,
        'workspace_id' => $agent->workspace_id,
        'feedback' => 'positive',
        'occurred_at' => now()->subMinutes(30),
    ]);

    expect(app(DashboardStatsQuery::class)->get($owner, null)->active_sessions)->toBe(1);
});

it('serves the team size from the cached value of the viewer\'s team', function (): void {
    $owner = User::factory()->create();
    $team = app(CreateDefaultTeamAction::class)->execute($owner);
    $role = $team->roles()->firstOrFail();

    $first = app(DashboardStatsQuery::class)->get($owner, $team);

    $team->members()->attach(User::factory()->employee($owner)->create()->id, ['role_id' => $role->id]);
    $this->travel(4)->minutes();
    $cached = app(DashboardStatsQuery::class)->get($owner, $team);

    $this->travelTo(Carbon::parse('2026-06-16 09:00:00', 'UTC'));
    $nextDay = app(DashboardStatsQuery::class)->get($owner, $team);

    expect($first->team->members)->toBe(0)
        ->and($cached->team->members)->toBe(0)
        ->and($nextDay->team->members)->toBe(1)
        // Without a team there is no team size, and no value shared with it.
        ->and(app(DashboardStatsQuery::class)->get($owner, null)->team->members)->toBe(0);
});
