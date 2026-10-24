<?php

use Domain\Analytics\Actions\Recording\RecordSourceRetrievalAction;
use Domain\Analytics\Data\Recording\SourceRetrievalRecordData;
use Domain\Analytics\Events\SourceRetrieved;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Each knowledge retrieval counts once per source in the agent's daily source
 * rollup, which keeps the source's latest name and type for the Sources page.
 */
it('upserts and increments per-source retrieval rollups', function (): void {
    $action = app(RecordSourceRetrievalAction::class);
    $agentId = 940001;
    $workspaceId = 500001;
    $type = (new SourceFile)->getMorphClass();
    $when = Carbon::parse('2026-06-01 09:00');

    $action->execute(SourceRetrievalRecordData::fromEvent(new SourceRetrieved($agentId, $workspaceId, [
        ['type' => $type, 'id' => 10],
        ['type' => $type, 'id' => 11],
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

it('does nothing for an empty source list', function (): void {
    $action = app(RecordSourceRetrievalAction::class);
    $agentId = 940002;

    $action->execute(SourceRetrievalRecordData::fromEvent(new SourceRetrieved($agentId, 500002, [], Carbon::parse('2026-06-01 09:00'))));

    expect(DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_source_daily')->where('agent_id', $agentId)->count())->toBe(0);
});

it('counts a source once per retrieval even when it is listed twice', function (): void {
    $action = app(RecordSourceRetrievalAction::class);
    $agentId = 940003;
    $type = (new SourceFile)->getMorphClass();

    $action->execute(SourceRetrievalRecordData::fromEvent(new SourceRetrieved($agentId, 500003, [
        ['type' => $type, 'id' => 12],
        ['type' => $type, 'id' => 5],
        ['type' => $type, 'id' => 12],
    ], Carbon::parse('2026-06-01 09:00'))));

    $rollup = DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_source_daily')
        ->where('agent_id', $agentId);

    expect((int) (clone $rollup)->where('sourceable_id', 12)->value('retrieval_count'))->toBe(1)
        ->and((int) (clone $rollup)->where('sourceable_id', 5)->value('retrieval_count'))->toBe(1);
});

it('stores the source name and mime, and refreshes them on the next retrieval', function (): void {
    // The rollup keeps the label so the Sources page can still name a source
    // after it is deleted; a renamed source shows its latest name.
    $action = app(RecordSourceRetrievalAction::class);
    $agentId = 940004;
    $workspaceId = 500004;
    $type = (new SourceFile)->getMorphClass();
    $when = Carbon::parse('2026-06-01 09:00');

    $action->execute(SourceRetrievalRecordData::fromEvent(new SourceRetrieved($agentId, $workspaceId, [
        ['type' => $type, 'id' => 13, 'name' => 'prices.pdf', 'mime' => 'application/pdf'],
    ], $when)));

    $row = fn (): object => DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_source_daily')
        ->where('agent_id', $agentId)
        ->where('sourceable_type', $type)
        ->where('sourceable_id', 13)
        ->sole();

    expect($row()->source_name)->toBe('prices.pdf')
        ->and($row()->source_mime)->toBe('application/pdf')
        ->and($row()->workspace_id)->toBe($workspaceId);

    $action->execute(SourceRetrievalRecordData::fromEvent(new SourceRetrieved($agentId, $workspaceId, [
        ['type' => $type, 'id' => 13, 'name' => 'prices-2026.docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ], $when)));

    expect($row()->source_name)->toBe('prices-2026.docx')
        ->and($row()->source_mime)->toBe('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->and((int) $row()->retrieval_count)->toBe(2);
});
