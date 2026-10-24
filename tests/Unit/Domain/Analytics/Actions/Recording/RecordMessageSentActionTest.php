<?php

use Domain\Analytics\Actions\Recording\RecordMessageSentAction;
use Domain\Analytics\Data\Recording\MessageSentRecordData;
use Domain\Analytics\Events\MessageSent;
use Domain\Analytics\Services\AnalyticsDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * RecordMessageSentAction stores each message as an analytics event and folds
 * it into the conversation metrics and the hourly rollup: tokens, and for an
 * AI reply the answer figures (replies that found no answer, the sum and count
 * of their confidence) the satisfaction numbers read. The queued listener runs
 * on an at-least-once queue, so a redelivered message must change nothing.
 */
function rmsaEvent(int $agentId, string $role = 'assistant', ?bool $found = null, ?float $confidence = null, ?int $chatterId = null, ?string $messageId = null, string $conversationId = '00000000-0000-4000-8000-000000000720'): MessageSent
{
    return new MessageSent(
        agentId: $agentId,
        workspaceId: 720000,
        chatterId: $chatterId,
        conversationExternalId: $conversationId,
        messageExternalId: $messageId ?? (string) Str::uuid(),
        sessionExternalId: '00000000-0000-4000-9000-000000000720',
        role: $role,
        llmId: 7,
        countryCode: 'DE',
        responseTimeMs: $role === 'assistant' ? 500 : null,
        promptTokens: $role === 'assistant' ? 100 : null,
        completionTokens: $role === 'assistant' ? 50 : null,
        occurredAt: Carbon::parse('2026-06-01 10:15'),
        hasFoundAnswer: $found,
        confidenceScore: $confidence,
    );
}

function rmsaRecord(MessageSent $event): void
{
    app(RecordMessageSentAction::class)->execute(MessageSentRecordData::fromEvent($event));
}

it('counts the replies that found no answer and sums their confidence in the hourly rollup', function (): void {
    $agentId = 970001;

    rmsaRecord(rmsaEvent($agentId, 'user', true));
    rmsaRecord(rmsaEvent($agentId, 'assistant', true, 0.9));
    rmsaRecord(rmsaEvent($agentId, 'assistant', false, 0.4));
    // Could not be told, and no confidence: counted as a reply only.
    rmsaRecord(rmsaEvent($agentId));

    $rollup = app(AnalyticsDatabase::class)->table('analytics_agent_hourly_rollups')->where('agent_id', $agentId)->sole();

    expect((int) $rollup->assistant_messages)->toBe(3)
        ->and((int) $rollup->user_messages)->toBe(1)
        ->and((int) $rollup->unanswered_replies)->toBe(1)
        ->and((float) $rollup->confidence_sum)->toBe(1.3)
        ->and((int) $rollup->confidence_count)->toBe(2);
});

it('takes no answer figures from a visitor message', function (): void {
    $agentId = 970002;

    rmsaRecord(rmsaEvent($agentId, 'user', false, 0.5));

    $rollup = app(AnalyticsDatabase::class)->table('analytics_agent_hourly_rollups')->where('agent_id', $agentId)->sole();

    expect((int) $rollup->unanswered_replies)->toBe(0)
        ->and((float) $rollup->confidence_sum)->toBe(0.0)
        ->and((int) $rollup->confidence_count)->toBe(0);
});

it('records a redelivered message exactly once across events, metrics and rollups', function (): void {
    $agentId = 970003;
    $conversationId = (string) Str::uuid();
    $event = rmsaEvent($agentId, messageId: (string) Str::uuid(), conversationId: $conversationId);

    rmsaRecord($event);
    rmsaRecord($event); // queue redelivery

    $db = app(AnalyticsDatabase::class);
    $rollup = $db->table('analytics_agent_hourly_rollups')->where('agent_id', $agentId)->sole();

    expect($db->table('analytics_events')->where('event_type', 'message_sent')->where('message_external_id', $event->messageExternalId)->count())->toBe(1)
        ->and($db->table('analytics_conversation_metrics')->where('conversation_external_id', $conversationId)->value('message_count'))->toBe(1)
        ->and((int) $rollup->assistant_messages)->toBe(1)
        ->and((int) $rollup->prompt_tokens)->toBe(100)
        ->and((int) $rollup->completion_tokens)->toBe(50)
        ->and((int) $rollup->total_tokens)->toBe(150);
});

it('stores the chatter a message was sent by, and none for an anonymous one', function (): void {
    $byChatter = rmsaEvent(970004, chatterId: 970104);
    $anonymous = rmsaEvent(970004);

    rmsaRecord($byChatter);
    rmsaRecord($anonymous);

    $chatterOf = fn (MessageSent $event): mixed => app(AnalyticsDatabase::class)->table('analytics_events')
        ->where('message_external_id', $event->messageExternalId)
        ->value('chatter_id');

    expect($chatterOf($byChatter))->toBe(970104)
        ->and($chatterOf($anonymous))->toBeNull();
});
