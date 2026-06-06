<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\AgentMessageDimensionByDateAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * AgentMessageDimensionByDateAction now reads the pre-aggregated daily rollups
 * (analytics_agent_{sentiment,tone}_daily) instead of scanning chat_messages.
 * 'total' and 'unspecified' are derived from the message rollup's user_messages
 * (total user messages) minus the analysed counts.
 */
function seedDimensionDaily(string $table, int $agentId, string $column, string $day, string $value, int $count): void
{
    DB::connection(config('chatvia.analytics.connection'))->table($table)->insert([
        'agent_id' => $agentId,
        'workspace_id' => DB::table('agents')->where('id', $agentId)->value('user_id'),
        'bucket_day' => $day,
        $column => $value,
        'message_count' => $count,
    ]);
}

function seedUserMessageTotal(int $agentId, string $day, int $count): void
{
    // Total user messages live on the hourly message rollup; one bucket row per
    // day is enough for the read action's SUM(user_messages) by date.
    DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_hourly_rollups')->insert([
        'agent_id' => $agentId,
        'workspace_id' => DB::table('agents')->where('id', $agentId)->value('user_id'),
        'bucket_hour' => $day.' 10:00:00+00',
        'country_code' => '--',
        'llm_external_name' => '',
        'user_messages' => $count,
    ]);
}

it('aggregates per-day bucket counts and range totals from the rollups', function (): void {
    $agent = Agent::factory()->create();

    // Day 1: tone neutral=2, frustrated=1 (3 analysed); 4 user messages → 1 unspecified
    seedDimensionDaily('analytics_agent_tone_daily', $agent->id, 'tone', '2026-06-01', 'neutral', 2);
    seedDimensionDaily('analytics_agent_tone_daily', $agent->id, 'tone', '2026-06-01', 'frustrated', 1);
    seedUserMessageTotal($agent->id, '2026-06-01', 4);

    // Day 2: tone urgent=1; 1 user message → 0 unspecified
    seedDimensionDaily('analytics_agent_tone_daily', $agent->id, 'tone', '2026-06-02', 'urgent', 1);
    seedUserMessageTotal($agent->id, '2026-06-02', 1);

    $result = app(AgentMessageDimensionByDateAction::class)->execute(
        agent: $agent,
        column: 'tone',
        bucketValues: ['neutral', 'frustrated', 'urgent', 'confused', 'satisfied'],
        startDate: Carbon::parse('2026-06-01')->startOfDay(),
        endDate: Carbon::parse('2026-06-03')->endOfDay(),
    );

    expect($result['byDate'])->toHaveCount(3); // 3 calendar days even though day 3 is empty

    $day1 = collect($result['byDate'])->firstWhere('date', '2026-06-01');
    expect($day1['neutral'])->toEqual(2)
        ->and($day1['frustrated'])->toEqual(1)
        ->and($day1['urgent'])->toEqual(0)
        ->and($day1['unspecified'])->toEqual(1);

    $day2 = collect($result['byDate'])->firstWhere('date', '2026-06-02');
    expect($day2['urgent'])->toEqual(1)
        ->and($day2['neutral'])->toEqual(0);

    $day3 = collect($result['byDate'])->firstWhere('date', '2026-06-03');
    expect($day3['neutral'])->toEqual(0)
        ->and($day3['unspecified'])->toEqual(0);

    expect($result['totals'])->toBe([
        'neutral' => 2,
        'frustrated' => 1,
        'urgent' => 1,
        'confused' => 0,
        'satisfied' => 0,
        'unspecified' => 1,
        'total' => 5,
    ]);
});

it('scopes the aggregate to the requested agent', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create();

    seedDimensionDaily('analytics_agent_sentiment_daily', $agent->id, 'sentiment', '2026-06-01', 'positive', 1);
    seedUserMessageTotal($agent->id, '2026-06-01', 1);

    seedDimensionDaily('analytics_agent_sentiment_daily', $other->id, 'sentiment', '2026-06-01', 'positive', 9);
    seedUserMessageTotal($other->id, '2026-06-01', 9);

    $result = app(AgentMessageDimensionByDateAction::class)->execute(
        agent: $agent,
        column: 'sentiment',
        bucketValues: ['positive', 'negative', 'neutral'],
        startDate: Carbon::parse('2026-06-01')->startOfDay(),
        endDate: Carbon::parse('2026-06-01')->endOfDay(),
    );

    expect($result['totals']['positive'])->toBe(1)
        ->and($result['totals']['total'])->toBe(1);
});

it('rejects an unsupported dimension column', function (): void {
    $agent = Agent::factory()->create();

    app(AgentMessageDimensionByDateAction::class)->execute(
        agent: $agent,
        column: 'country',
        bucketValues: ['x'],
        startDate: Carbon::parse('2026-06-01')->startOfDay(),
        endDate: Carbon::parse('2026-06-01')->endOfDay(),
    );
})->throws(InvalidArgumentException::class);
