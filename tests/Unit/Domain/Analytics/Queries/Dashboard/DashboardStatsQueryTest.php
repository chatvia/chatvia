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

it('counts the workspace\'s own agents, conversations, active sessions and limit usage', function (): void {
    $stats = gdsWorkspaceStats()->toArray();

    expect($stats['agents']['total'])->toBe(2)
        ->and($stats['conversations'])->toBe(5)
        ->and($stats['active_sessions'])->toBe(2)
        ->and($stats['messages']['total'])->toBe(11)
        ->and($stats['messages']['limit'])->toBe(1000)
        ->and($stats['messages']['available'])->toBe(989)
        ->and($stats['messages']['trend'])->toBe(['this_month' => 9, 'last_month' => 0, 'percent_change' => 100]);
});

it('counts this month\'s replies from the rollup when the workspace has no limit', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    analyticsRollup($agent, '2026-06-10 10:00:00+00', ['assistant_messages' => 4]);
    analyticsRollup($agent, '2026-05-20 10:00:00+00', ['assistant_messages' => 6]);

    $stats = app(DashboardStatsQuery::class)->get($owner, null)->toArray();

    expect($stats['messages']['total'])->toBe(4)
        ->and($stats['messages']['limit'])->toBeNull()
        ->and($stats['messages']['available'])->toBeNull();
});
