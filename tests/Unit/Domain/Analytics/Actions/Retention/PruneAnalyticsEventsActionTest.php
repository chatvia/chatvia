<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Retention\PruneAnalyticsEventsAction;
use Domain\Analytics\Services\AnalyticsDatabase;
use Illuminate\Support\Str;

/**
 * The install-wide analytics retention deletes old raw events, conversation
 * metrics and first-seen sessions, and keeps feedback, the rollups and every
 * row inside the window. The events are split into monthly partitions when
 * pg_partman is installed; without it they all sit in the default partition,
 * and the same rows go either way.
 */
function paeEvent(string $occurredAt): void
{
    app(AnalyticsDatabase::class)->table('analytics_events')->insert([
        'agent_id' => 910001,
        'workspace_id' => 910002,
        'message_external_id' => (string) Str::uuid(),
        'occurred_at' => $occurredAt,
    ]);
}

function paeFeedback(string $occurredAt): void
{
    app(AnalyticsDatabase::class)->table('analytics_message_feedback')->insert([
        'message_external_id' => (string) Str::uuid(),
        'agent_id' => 910001,
        'workspace_id' => 910002,
        'feedback' => 'positive',
        'occurred_at' => $occurredAt,
    ]);
}

function paeMetrics(string $startedAt, string $endedAt): string
{
    $id = (string) Str::uuid();

    app(AnalyticsDatabase::class)->table('analytics_conversation_metrics')->insert([
        'conversation_external_id' => $id,
        'agent_id' => 910001,
        'workspace_id' => 910002,
        'started_at' => $startedAt,
        'ended_at' => $endedAt,
        'message_count' => 2,
    ]);

    return $id;
}

function paeSessionFirst(string $firstSeenAt): string
{
    $id = (string) Str::uuid();

    app(AnalyticsDatabase::class)->table('analytics_session_firsts')->insert([
        'agent_id' => 910001,
        'workspace_id' => 910002,
        'session_external_id' => $id,
        'first_seen_at' => $firstSeenAt,
    ]);

    return $id;
}

it('deletes old message events but keeps feedback and recent rows', function (): void {
    paeEvent(now()->subDays(400)->toDateTimeString());
    paeEvent(now()->subDays(10)->toDateTimeString());
    paeFeedback(now()->subDays(400)->toDateTimeString());

    $deleted = app(PruneAnalyticsEventsAction::class)->execute(now()->subDays(395));

    $db = app(AnalyticsDatabase::class);

    expect($deleted)->toBe(1)
        ->and($db->table('analytics_events')->where('workspace_id', 910002)->count())->toBe(1)
        ->and($db->table('analytics_message_feedback')->where('workspace_id', 910002)->count())->toBe(1);
});

it('deletes the metrics of old, quiet conversations and old first-seen sessions', function (): void {
    $old = paeMetrics(now()->subDays(420)->toDateTimeString(), now()->subDays(419)->toDateTimeString());
    // Started long ago but still in use: its next message must not count as a new conversation.
    $ongoing = paeMetrics(now()->subDays(420)->toDateTimeString(), now()->subDays(3)->toDateTimeString());
    $recent = paeMetrics(now()->subDays(5)->toDateTimeString(), now()->subDays(5)->toDateTimeString());
    $oldSession = paeSessionFirst(now()->subDays(400)->toDateTimeString());
    $recentSession = paeSessionFirst(now()->subDays(10)->toDateTimeString());

    $deleted = app(PruneAnalyticsEventsAction::class)->execute(now()->subDays(395));

    $metrics = app(AnalyticsDatabase::class)->table('analytics_conversation_metrics')->whereIn('conversation_external_id', [$old, $ongoing, $recent])->pluck('conversation_external_id')->sort()->values()->all();
    $sessions = app(AnalyticsDatabase::class)->table('analytics_session_firsts')->whereIn('session_external_id', [$oldSession, $recentSession])->pluck('session_external_id')->all();

    expect($deleted)->toBe(2)
        ->and($metrics)->toBe(collect([$ongoing, $recent])->sort()->values()->all())
        ->and($sessions)->toBe([$recentSession]);
});

it('keeps the hourly and daily rollups however old', function (): void {
    $agent = Agent::factory()->create();
    analyticsRollup($agent, now()->subDays(800)->startOfHour()->toDateTimeString(), ['assistant_messages' => 5]);

    app(PruneAnalyticsEventsAction::class)->execute(now()->subDays(395));

    expect(app(AnalyticsDatabase::class)->table('analytics_agent_hourly_rollups')->where('agent_id', $agent->id)->exists())->toBeTrue()
        ->and(app(AnalyticsDatabase::class)->table('analytics_agent_daily')->where('agent_id', $agent->id)->exists())->toBeTrue();
});

it('drops the monthly partitions that ended before the cutoff, counting their rows', function (): void {
    $db = app(AnalyticsDatabase::class)->connection();
    $partition = fn (): ?string => $db->scalar('SELECT tableoid::regclass::text FROM analytics_events WHERE workspace_id = 910002 LIMIT 1');

    paeEvent(now()->toDateTimeString());
    paeEvent(now()->toDateTimeString());
    $current = $partition();

    $deleted = app(PruneAnalyticsEventsAction::class)->execute(now()->addMonthNoOverflow()->startOfMonth()->addDay());

    expect($current)->not->toBe('analytics_events_default')
        // The seeded demo traffic of the testing database goes too.
        ->and($deleted)->toBeGreaterThanOrEqual(2)
        ->and($db->scalar('SELECT to_regclass(?)', [$current]))->toBeNull()
        ->and($db->table('analytics_events')->where('workspace_id', 910002)->count())->toBe(0);
})->skip(fn (): bool => blank(app(AnalyticsDatabase::class)->connection()->scalar("SELECT 1 FROM pg_extension WHERE extname = 'pg_partman'")), 'needs pg_partman');
