<?php

use Domain\Analytics\Actions\Recording\RecordMessageSentAction;
use Domain\Analytics\Data\Recording\MessageSentRecordData;
use Domain\Analytics\Events\MessageSent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Regression: the queued analytics listener runs on an at-least-once queue,
 * and a redelivered job used to replay the whole transaction — a duplicate
 * analytics_events row plus double-counted conversation metrics and hourly
 * rollups (which feed the customer-visible "messages used" figure). The
 * message_sent partial unique + insertOrIgnore gate makes replays no-ops.
 */
function analyticsDb()
{
    return DB::connection(config('chatvia.analytics.connection'));
}

function recordMessageSent(int $agentId, string $convId, string $msgId, string $sessionId, ?int $chatterId = null): void
{
    app(RecordMessageSentAction::class)->execute(MessageSentRecordData::fromEvent(new MessageSent(
        agentId: $agentId,
        workspaceId: 710000,
        chatterId: $chatterId,
        conversationExternalId: $convId,
        messageExternalId: $msgId,
        sessionExternalId: $sessionId,
        role: 'assistant',
        llmExternalName: 'qwen3',
        countryCode: 'DE',
        responseTimeMs: 850,
        promptTokens: 100,
        completionTokens: 50,
        occurredAt: Carbon::parse('2026-06-01 10:00'),
    )));
}

it('records a redelivered message_sent exactly once across events, metrics and rollups', function (): void {
    $agentId = 960001;
    $convId = (string) Str::uuid();
    $msgId = (string) Str::uuid();
    $sessionId = (string) Str::uuid();

    recordMessageSent($agentId, $convId, $msgId, $sessionId);
    recordMessageSent($agentId, $convId, $msgId, $sessionId); // queue redelivery

    $events = analyticsDb()->table('analytics_events')
        ->where('event_type', 'message_sent')
        ->where('message_external_id', $msgId);

    $metrics = analyticsDb()->table('analytics_conversation_metrics')
        ->where('conversation_external_id', $convId)
        ->first();

    $rollup = analyticsDb()->table('analytics_agent_hourly_rollups')
        ->where('agent_id', $agentId)
        ->first();

    expect($events->count())->toBe(1)
        ->and($metrics->message_count)->toBe(1)
        ->and((int) $rollup->assistant_messages)->toBe(1)
        ->and((int) $rollup->prompt_tokens)->toBe(100)
        ->and((int) $rollup->completion_tokens)->toBe(50)
        ->and((int) $rollup->total_tokens)->toBe(150);
});

it('stores the chatter a message was sent by, and none for an anonymous one', function (): void {
    $convId = (string) Str::uuid();
    $sessionId = (string) Str::uuid();
    $chatterMsg = (string) Str::uuid();
    $anonymousMsg = (string) Str::uuid();

    recordMessageSent(960002, $convId, $chatterMsg, $sessionId, chatterId: 960102);
    recordMessageSent(960002, $convId, $anonymousMsg, $sessionId);

    $chatterOf = fn (string $msgId): mixed => analyticsDb()->table('analytics_events')
        ->where('message_external_id', $msgId)
        ->value('chatter_id');

    expect($chatterOf($chatterMsg))->toBe(960102)
        ->and($chatterOf($anonymousMsg))->toBeNull();
});
