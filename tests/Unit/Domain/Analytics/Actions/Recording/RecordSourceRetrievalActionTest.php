<?php

use Domain\Analytics\Actions\Recording\RecordSourceRetrievalAction;
use Domain\Analytics\Data\Recording\SourceRetrievalRecordData;
use Domain\Analytics\Events\SourceRetrieved;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Each knowledge retrieval counts once per source in the agent's daily source
 * rollup, even when the retrieval lists a source twice.
 */
it('counts each retrieval once per source in the daily rollup', function (): void {
    $action = app(RecordSourceRetrievalAction::class);
    $agentId = 940001;
    $workspaceId = 500001;
    $type = (new SourceFile)->getMorphClass();
    $when = Carbon::parse('2026-06-01 09:00');

    $action->execute(SourceRetrievalRecordData::fromEvent(new SourceRetrieved($agentId, $workspaceId, [
        ['type' => $type, 'id' => 10],
        ['type' => $type, 'id' => 11],
        ['type' => $type, 'id' => 10],
    ], $when)));
    $action->execute(SourceRetrievalRecordData::fromEvent(new SourceRetrieved($agentId, $workspaceId, [
        ['type' => $type, 'id' => 10],
    ], $when)));

    $rollup = DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_source_daily')
        ->where('agent_id', $agentId)
        ->where('bucket_day', '2026-06-01');

    expect((int) (clone $rollup)->where('sourceable_id', 10)->value('retrieval_count'))->toBe(2)
        ->and((int) (clone $rollup)->where('sourceable_id', 11)->value('retrieval_count'))->toBe(1);
});
