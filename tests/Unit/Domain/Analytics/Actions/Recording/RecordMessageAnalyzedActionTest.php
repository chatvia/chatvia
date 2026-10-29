<?php

use Domain\Analytics\Actions\Recording\RecordMessageAnalyzedAction;
use Domain\Analytics\Data\Recording\MessageAnalysisRecordData;
use Domain\Analytics\Events\MessageAnalyzed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * An analysed visitor message counts in the agent's daily sentiment and tone
 * rollups, each only when the analysis gave that dimension.
 */
function rmaDimensionCount(string $table, int $agentId, string $column, string $value): ?int
{
    $count = DB::connection(config('chatvia.analytics.connection'))->table($table)
        ->where('agent_id', $agentId)
        ->where('bucket_day', '2026-06-01')
        ->where($column, $value)
        ->value('message_count');

    return $count === null ? null : (int) $count;
}

it('counts each analysed dimension in its daily rollup and skips a missing one', function (): void {
    $action = app(RecordMessageAnalyzedAction::class);
    $agentId = 920001;
    $workspaceId = 600001;
    $when = Carbon::parse('2026-06-01 10:00');

    $action->execute(MessageAnalysisRecordData::fromEvent(new MessageAnalyzed($agentId, $workspaceId, 'positive', 'neutral', $when)));
    $action->execute(MessageAnalysisRecordData::fromEvent(new MessageAnalyzed($agentId, $workspaceId, 'positive', 'urgent', $when)));
    $action->execute(MessageAnalysisRecordData::fromEvent(new MessageAnalyzed($agentId, $workspaceId, 'positive', null, $when)));

    expect(rmaDimensionCount('analytics_agent_sentiment_daily', $agentId, 'sentiment', 'positive'))->toBe(3)
        ->and(rmaDimensionCount('analytics_agent_tone_daily', $agentId, 'tone', 'neutral'))->toBe(1)
        ->and(rmaDimensionCount('analytics_agent_tone_daily', $agentId, 'tone', 'urgent'))->toBe(1)
        ->and(DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_tone_daily')->where('agent_id', $agentId)->count())->toBe(2);
});
