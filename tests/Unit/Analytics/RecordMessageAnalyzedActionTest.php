<?php

use Domain\Analytics\Actions\RecordMessageAnalyzedAction;
use Domain\Analytics\Events\MessageAnalyzed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function dimensionCount(string $table, int $agentId, string $column, string $value): ?int
{
    $count = DB::connection(config('chatvia.analytics.connection'))->table($table)
        ->where('agent_id', $agentId)
        ->where('bucket_day', '2026-06-01')
        ->where($column, $value)
        ->value('message_count');

    return $count === null ? null : (int) $count;
}

it('upserts and increments the sentiment and tone daily rollups', function (): void {
    $action = app(RecordMessageAnalyzedAction::class);
    $agentId = 920001;
    $userId = 600001;
    $when = Carbon::parse('2026-06-01 10:00');

    $action->execute(new MessageAnalyzed($agentId, $userId, 'positive', 'neutral', $when));
    $action->execute(new MessageAnalyzed($agentId, $userId, 'positive', 'urgent', $when));

    expect(dimensionCount('analytics_agent_sentiment_daily', $agentId, 'sentiment', 'positive'))->toBe(2)
        ->and(dimensionCount('analytics_agent_tone_daily', $agentId, 'tone', 'neutral'))->toBe(1)
        ->and(dimensionCount('analytics_agent_tone_daily', $agentId, 'tone', 'urgent'))->toBe(1);
});

it('records sentiment even when tone is null', function (): void {
    $action = app(RecordMessageAnalyzedAction::class);
    $agentId = 920002;

    $action->execute(new MessageAnalyzed($agentId, 600002, 'negative', null, Carbon::parse('2026-06-01 10:00')));

    expect(dimensionCount('analytics_agent_sentiment_daily', $agentId, 'sentiment', 'negative'))->toBe(1)
        ->and(DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_tone_daily')->where('agent_id', $agentId)->count())->toBe(0);
});

it('skips entirely when sentiment and tone are both null', function (): void {
    $action = app(RecordMessageAnalyzedAction::class);
    $agentId = 920003;

    $action->execute(new MessageAnalyzed($agentId, 600003, null, null, Carbon::parse('2026-06-01 10:00')));

    expect(DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_sentiment_daily')->where('agent_id', $agentId)->count())->toBe(0);
});
