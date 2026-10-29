<?php

use Domain\Analytics\Actions\Recording\RecordFeedbackGivenAction;
use Domain\Analytics\Data\Recording\FeedbackRecordData;
use Domain\Analytics\Events\FeedbackGiven;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Feedback is stored as current state: one analytics row per message, kept in
 * step as a visitor switches or clears their reaction, so the counts are a
 * snapshot, never a lifetime tally.
 */
function rfgRows(string $messageId): Builder
{
    return DB::connection(config('chatvia.analytics.connection'))->table('analytics_message_feedback')
        ->where('message_external_id', $messageId);
}

function rfgGive(int $agentId, string $convId, string $msgId, ?string $feedback): void
{
    app(RecordFeedbackGivenAction::class)->execute(FeedbackRecordData::fromEvent(new FeedbackGiven(
        agentId: $agentId,
        workspaceId: 700000,
        chatterId: null,
        conversationExternalId: $convId,
        messageExternalId: $msgId,
        feedback: $feedback,
        llmId: 7,
        countryCode: 'US',
        occurredAt: Carbon::parse('2026-06-01 10:00'),
    )));
}

it('keeps one row per message for the current feedback and none once cleared', function (): void {
    $agentId = 950003;
    $convId = (string) Str::uuid7();
    $switched = (string) Str::uuid7();
    $cleared = (string) Str::uuid7();
    $kept = (string) Str::uuid7();

    rfgGive($agentId, $convId, $switched, 'positive');
    rfgGive($agentId, $convId, $switched, 'negative');
    rfgGive($agentId, $convId, $cleared, 'positive');
    rfgGive($agentId, $convId, $cleared, null);
    rfgGive($agentId, $convId, $kept, 'positive');

    $rows = DB::connection(config('chatvia.analytics.connection'))->table('analytics_message_feedback')
        ->where('agent_id', $agentId);

    expect(rfgRows($switched)->count())->toBe(1)
        ->and(rfgRows($switched)->value('feedback'))->toBe('negative')
        ->and(rfgRows($cleared)->count())->toBe(0)
        ->and((clone $rows)->where('feedback', 'positive')->count())->toBe(1)
        ->and((clone $rows)->where('feedback', 'negative')->count())->toBe(1);
});
