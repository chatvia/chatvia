<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Dimensions\BuildMessageDimensionBreakdownAction;
use Domain\Analytics\Data\DateRangeData;
use Domain\Analytics\Enums\MessageDimension;
use Illuminate\Support\Facades\DB;

/**
 * BuildMessageDimensionBreakdownAction reads the pre-aggregated daily rollups
 * (analytics_agent_{sentiment,tone}_daily) instead of scanning chat_messages.
 * 'total' and 'unspecified' are derived from the message rollup's user_messages
 * (total user messages) minus the analysed counts.
 */
/** One daily sentiment or tone rollup row of the agent. */
function bmdDaily(Agent $agent, MessageDimension $dimension, string $day, string $value, int $count): void
{
    $column = $dimension === MessageDimension::Tone ? 'tone' : 'sentiment';

    DB::connection(config('chatvia.analytics.connection'))->table("analytics_agent_{$column}_daily")->insert([
        'agent_id' => $agent->id,
        'workspace_id' => $agent->workspace_id,
        'bucket_day' => $day,
        $column => $value,
        'message_count' => $count,
    ]);
}

it('aggregates per-day bucket counts and range totals from the rollups', function (): void {
    $agent = Agent::factory()->create();

    // Day 1: tone neutral=2, frustrated=1 (3 analysed); 4 user messages → 1 unspecified
    bmdDaily($agent, MessageDimension::Tone, '2026-06-01', 'neutral', 2);
    bmdDaily($agent, MessageDimension::Tone, '2026-06-01', 'frustrated', 1);
    analyticsRollup($agent, '2026-06-01 10:00:00+00', ['user_messages' => 4]);

    // Day 2: tone urgent=1; 1 user message → 0 unspecified
    bmdDaily($agent, MessageDimension::Tone, '2026-06-02', 'urgent', 1);
    analyticsRollup($agent, '2026-06-02 10:00:00+00', ['user_messages' => 1]);

    $result = app(BuildMessageDimensionBreakdownAction::class)->execute(
        $agent,
        MessageDimension::Tone,
        DateRangeData::fromDates('2026-06-01', '2026-06-03'),
    );

    expect($result->byDate)->toHaveCount(3); // 3 calendar days even though day 3 is empty

    $day1 = collect($result->byDate)->firstWhere('date', '2026-06-01');
    expect($day1['neutral'])->toEqual(2)
        ->and($day1['frustrated'])->toEqual(1)
        ->and($day1['urgent'])->toEqual(0)
        ->and($day1['unspecified'])->toEqual(1);

    $day2 = collect($result->byDate)->firstWhere('date', '2026-06-02');
    expect($day2['urgent'])->toEqual(1)
        ->and($day2['neutral'])->toEqual(0);

    $day3 = collect($result->byDate)->firstWhere('date', '2026-06-03');
    expect($day3['neutral'])->toEqual(0)
        ->and($day3['unspecified'])->toEqual(0);

    expect($result->totals)->toBe([
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

    bmdDaily($agent, MessageDimension::Sentiment, '2026-06-01', 'positive', 1);
    analyticsRollup($agent, '2026-06-01 10:00:00+00', ['user_messages' => 1]);

    bmdDaily($other, MessageDimension::Sentiment, '2026-06-01', 'positive', 9);
    analyticsRollup($other, '2026-06-01 10:00:00+00', ['user_messages' => 9]);

    $result = app(BuildMessageDimensionBreakdownAction::class)->execute(
        $agent,
        MessageDimension::Sentiment,
        DateRangeData::fromDates('2026-06-01', '2026-06-01'),
    );

    expect($result->totals['positive'])->toBe(1)
        ->and($result->totals['total'])->toBe(1);
});
