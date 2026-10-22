<?php

use Domain\Analytics\Actions\Retention\PruneAnalyticsEventsAction;
use Domain\Analytics\Enums\AnalyticsEventType;
use Domain\Analytics\Services\AnalyticsDatabase;
use Illuminate\Support\Str;

/**
 * The install-wide analytics retention deletes old raw events, conversation
 * metrics and first-seen sessions, and keeps feedback, the rollups and every
 * row inside the window.
 */
function paeEvent(AnalyticsEventType $type, string $occurredAt): void
{
    app(AnalyticsDatabase::class)->table('analytics_events')->insert([
        'agent_id' => 910001,
        'workspace_id' => 910002,
        'event_type' => $type->value,
        'message_external_id' => (string) Str::uuid(),
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
    paeEvent(AnalyticsEventType::MessageSent, now()->subDays(400)->toDateTimeString());
    paeEvent(AnalyticsEventType::MessageSent, now()->subDays(10)->toDateTimeString());
    paeEvent(AnalyticsEventType::FeedbackGiven, now()->subDays(400)->toDateTimeString());

    $deleted = app(PruneAnalyticsEventsAction::class)->execute(now()->subDays(395));

    $remaining = app(AnalyticsDatabase::class)->table('analytics_events')->where('workspace_id', 910002)->pluck('event_type')->sort()->values()->all();

    expect($deleted)->toBe(1)
        ->and($remaining)->toBe([AnalyticsEventType::FeedbackGiven->value, AnalyticsEventType::MessageSent->value]);
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

it('keeps the hourly rollups however old', function (): void {
    app(AnalyticsDatabase::class)->table('analytics_agent_hourly_rollups')->insert([
        'agent_id' => 910003,
        'workspace_id' => 910002,
        'bucket_hour' => now()->subDays(800)->startOfHour(),
        'country_code' => '--',
        'llm_id' => 0,
        'assistant_messages' => 5,
    ]);

    app(PruneAnalyticsEventsAction::class)->execute(now()->subDays(395));

    expect(app(AnalyticsDatabase::class)->table('analytics_agent_hourly_rollups')->where('agent_id', 910003)->exists())->toBeTrue();
});
