<?php

use Domain\Analytics\Actions\Recording\RecordFeedbackGivenAction;
use Domain\Analytics\Data\Recording\FeedbackRecordData;
use Domain\Analytics\Events\FeedbackGiven;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Feedback is stored as current state: one analytics row per message, kept in
 * step as a visitor switches or clears their reaction, carrying the chatter
 * of the rated message's session (none for an anonymous or staff session).
 */
function feedbackRows(string $messageId)
{
    return DB::connection(config('chatvia.analytics.connection'))->table('analytics_events')
        ->where('event_type', 'feedback_given')
        ->where('message_external_id', $messageId);
}

function giveFeedback(int $agentId, string $convId, string $msgId, ?string $feedback, ?int $chatterId = null): void
{
    app(RecordFeedbackGivenAction::class)->execute(FeedbackRecordData::fromEvent(new FeedbackGiven(
        agentId: $agentId,
        workspaceId: 700000,
        chatterId: $chatterId,
        conversationExternalId: $convId,
        messageExternalId: $msgId,
        feedback: $feedback,
        llmExternalName: 'qwen3',
        countryCode: 'US',
        occurredAt: Carbon::parse('2026-06-01 10:00'),
    )));
}

it('stores exactly one feedback row per message and overwrites on switch', function (): void {
    $convId = (string) Str::uuid();
    $msgId = (string) Str::uuid();

    giveFeedback(950001, $convId, $msgId, 'positive');
    expect(feedbackRows($msgId)->count())->toBe(1)
        ->and(feedbackRows($msgId)->value('feedback'))->toBe('positive');

    // Switch 👍 → 👎: still ONE row, now negative — no lifetime double-count.
    giveFeedback(950001, $convId, $msgId, 'negative');
    expect(feedbackRows($msgId)->count())->toBe(1)
        ->and(feedbackRows($msgId)->value('feedback'))->toBe('negative');
});

it('removes the row when feedback is cleared', function (): void {
    $convId = (string) Str::uuid();
    $msgId = (string) Str::uuid();

    giveFeedback(950002, $convId, $msgId, 'positive');
    expect(feedbackRows($msgId)->count())->toBe(1);

    giveFeedback(950002, $convId, $msgId, null);
    expect(feedbackRows($msgId)->count())->toBe(0);
});

it('counts current feedback as a snapshot, not a lifetime tally', function (): void {
    $agentId = 950003;
    $convId = (string) Str::uuid();
    $switched = (string) Str::uuid();
    $cleared = (string) Str::uuid();
    $kept = (string) Str::uuid();

    giveFeedback($agentId, $convId, $switched, 'positive');
    giveFeedback($agentId, $convId, $switched, 'negative'); // switched → negative only
    giveFeedback($agentId, $convId, $cleared, 'positive');
    giveFeedback($agentId, $convId, $cleared, null);         // cleared → counts nothing
    giveFeedback($agentId, $convId, $kept, 'positive');      // stays positive

    $rows = DB::connection(config('chatvia.analytics.connection'))->table('analytics_events')
        ->where('event_type', 'feedback_given')
        ->where('agent_id', $agentId);

    expect((clone $rows)->where('feedback', 'positive')->count())->toBe(1)
        ->and((clone $rows)->where('feedback', 'negative')->count())->toBe(1);
});

it('keeps the chatter of the rated message on the feedback row', function (): void {
    $convId = (string) Str::uuid();
    $chatterMsg = (string) Str::uuid();
    $anonymousMsg = (string) Str::uuid();

    giveFeedback(950004, $convId, $chatterMsg, 'positive', chatterId: 950104);
    giveFeedback(950004, $convId, $anonymousMsg, 'negative');

    expect(feedbackRows($chatterMsg)->value('chatter_id'))->toBe(950104)
        ->and(feedbackRows($anonymousMsg)->value('chatter_id'))->toBeNull();
});
