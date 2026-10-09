<?php

use Domain\Analytics\Actions\Recording\RecordSourceRetrievalAction;
use Domain\Analytics\Data\Recording\SourceRetrievalRecordData;
use Domain\Analytics\Events\SourceRetrieved;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('upserts and increments per-source retrieval rollups', function (): void {
    $action = app(RecordSourceRetrievalAction::class);
    $agentId = 940001;
    $userId = 500001;
    $type = 'Domain\\Knowledge\\Source\\File\\Models\\SourceFile';
    $when = Carbon::parse('2026-06-01 09:00');

    $action->execute(SourceRetrievalRecordData::fromEvent(new SourceRetrieved($agentId, $userId, [
        ['type' => $type, 'id' => 10],
        ['type' => $type, 'id' => 11],
    ], $when)));
    $action->execute(SourceRetrievalRecordData::fromEvent(new SourceRetrieved($agentId, $userId, [
        ['type' => $type, 'id' => 10],
    ], $when)));

    $rollup = DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_source_daily')
        ->where('agent_id', $agentId)
        ->where('bucket_day', '2026-06-01');

    expect((int) (clone $rollup)->where('sourceable_id', 10)->value('retrieval_count'))->toBe(2)
        ->and((int) (clone $rollup)->where('sourceable_id', 11)->value('retrieval_count'))->toBe(1);
});

it('does nothing for an empty source list', function (): void {
    $action = app(RecordSourceRetrievalAction::class);
    $agentId = 940002;

    $action->execute(SourceRetrievalRecordData::fromEvent(new SourceRetrieved($agentId, 500002, [], Carbon::parse('2026-06-01 09:00'))));

    expect(DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_source_daily')->where('agent_id', $agentId)->count())->toBe(0);
});
