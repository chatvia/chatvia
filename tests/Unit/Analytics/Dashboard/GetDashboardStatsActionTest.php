<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Dashboard\GetDashboardStatsAction;
use Domain\Billing\Models\UserCredit;
use Domain\Billing\Models\UserQuota;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * GetDashboardStatsAction aggregates the whole dashboard payload from the
 * analytics rollup + events stream, keyed on the workspace owner's id. These
 * tests pin the structure and the representative scoping rules: only the
 * owner's agents/rollups count, active sessions are 24h-distinct, and the
 * messages panel reads the active UserCredit cycle.
 */
function gdsRollup(Agent $agent, string $bucketHour, array $values = []): void
{
    DB::connection(config('chatvia.analytics.connection'))
        ->table('analytics_agent_hourly_rollups')
        ->insert(array_merge([
            'agent_id' => $agent->id,
            'workspace_id' => $agent->user_id,
            'bucket_hour' => $bucketHour,
            'country_code' => '--',
            'llm_external_name' => '',
        ], $values));
}

function gdsEvent(Agent $agent, string $sessionExternalId, Carbon $occurredAt): void
{
    DB::connection(config('chatvia.analytics.connection'))
        ->table('analytics_events')
        ->insert([
            'agent_id' => $agent->id,
            'workspace_id' => $agent->user_id,
            'event_type' => 'message_sent',
            'session_external_id' => $sessionExternalId,
            'occurred_at' => $occurredAt,
        ]);
}

it('aggregates agents, conversations, messages and sessions for the workspace owner', function (): void {
    // Mid-month, mid-day: keeps the trend (month-bucketed) and the daily
    // volume windows deterministic.
    $this->travelTo(Carbon::parse('2026-06-15 12:00:00', 'UTC'));

    $owner = User::factory()->create();
    $team = app(TeamService::class)->createDefaultTeam($owner);
    UserQuota::firstOrCreate(
        ['user_id' => $owner->id],
        config('billing.packages.starter.quota_limits.fixed', []),
    );

    // Active billing cycle: 1000 base messages, started 5 days ago. The billed
    // counter (11) deliberately differs from the rollup sum (9) — as when 2 of
    // the replies were eval cases, which bill but never reach the analytics
    // rollup — to pin which source each panel reads.
    $credit = UserCredit::create([
        'user_id' => $owner->id,
        'messages' => 1000,
        'started_at' => now()->subDays(5),
        'ends_at' => now()->addDays(25),
    ]);
    $credit->usage()->create(['messages' => 11]);

    $agentA = createAgent($owner, ['name' => 'Agent A']);
    $agentB = createAgent($owner, ['name' => 'Agent B']);

    gdsRollup($agentA, '2026-06-15 10:00:00+00', ['conversations_started' => 3, 'assistant_messages' => 5]);
    gdsRollup($agentB, '2026-06-14 22:00:00+00', ['conversations_started' => 2, 'assistant_messages' => 4]);

    // Another workspace's traffic must not leak into this dashboard.
    $foreignAgent = Agent::factory()->create();
    gdsRollup($foreignAgent, '2026-06-15 10:00:00+00', ['conversations_started' => 7, 'assistant_messages' => 9]);

    // Active sessions: distinct session ids in the last 24h. Session A twice
    // (still one visitor), session B once, one stale, one foreign.
    $sessionA = (string) Str::uuid();
    $sessionB = (string) Str::uuid();
    gdsEvent($agentA, $sessionA, now()->subHours(2));
    gdsEvent($agentA, $sessionA, now()->subHours(1));
    gdsEvent($agentB, $sessionB, now()->subHours(3));
    gdsEvent($agentA, (string) Str::uuid(), now()->subDays(2));
    gdsEvent($foreignAgent, (string) Str::uuid(), now()->subHours(1));

    $stats = app(GetDashboardStatsAction::class)->execute($owner, $team)->toArray();

    expect($stats)->toHaveKeys([
        'agents', 'messages', 'conversations', 'active_sessions',
        'knowledge_sources', 'team', 'weekly_usage', 'monthly_usage', 'usage',
    ]);

    expect($stats['agents']['total'])->toBe(2)
        ->and($stats['conversations'])->toBe(5)
        ->and($stats['active_sessions'])->toBe(2)
        ->and($stats['team']['members'])->toBe(0);

    // Messages panel: used from the BILLING counter (covers eval cases the
    // rollup never sees); base quota from the UserCredit row; no addons.
    expect($stats['messages']['total'])->toBe(11)
        ->and($stats['messages']['limit'])->toBe(1000)
        ->and($stats['messages']['available'])->toBe(989)
        ->and($stats['messages']['cycle'])->not->toBeNull();

    // Trend (team present): all 9 assistant messages landed this month.
    expect($stats['messages']['trend']['this_month'])->toBe(9)
        ->and($stats['messages']['trend']['last_month'])->toBe(0)
        ->and($stats['messages']['trend']['percent_change'])->toBe(100);

    // Daily volume series: fixed-length windows whose values sum to the
    // workspace's assistant messages (both rollup days are inside 7 days).
    expect($stats['weekly_usage'])->toHaveCount(7)
        ->and(array_sum(array_column($stats['weekly_usage'], 'value')))->toBe(9)
        ->and($stats['monthly_usage'])->toHaveCount(30)
        ->and(array_sum(array_column($stats['monthly_usage'], 'value')))->toBe(9);

    // No sources attached to any agent.
    expect($stats['knowledge_sources'])->toBe([
        'files' => 0,
        'websites' => 0,
        'crawled_pages' => 0,
        'total' => 0,
    ]);

    // Usage panel reads the same credit math plus the quota row limits.
    expect($stats['usage']['plan'])->toBeString()
        ->and($stats['usage']['messages'])->toBe(['used' => 11, 'limit' => 1000, 'remaining' => 989])
        ->and($stats['usage']['pages']['limit'])->toBe(config('billing.packages.starter.quota_limits.fixed.pages_limit'))
        ->and($stats['usage']['pages']['used'])->toBe(0)
        ->and($stats['usage']['minutes']['used'])->toBe(0)
        ->and($stats['usage']['links']['used'])->toBe(0);
});

it('returns a zeroed trend and member count when no team is passed', function (): void {
    $owner = User::factory()->create();
    UserQuota::firstOrCreate(
        ['user_id' => $owner->id],
        config('billing.packages.starter.quota_limits.fixed', []),
    );

    $stats = app(GetDashboardStatsAction::class)->execute($owner, null)->toArray();

    expect($stats['messages']['trend'])->toBe(['this_month' => 0, 'last_month' => 0, 'percent_change' => 0])
        ->and($stats['team']['members'])->toBe(0)
        ->and($stats['agents']['total'])->toBe(0)
        ->and($stats['conversations'])->toBe(0)
        // No UserCredit row: panel reports an empty cycle, not an error.
        ->and($stats['messages']['total'])->toBe(0)
        ->and($stats['messages']['cycle'])->toBeNull();
});
