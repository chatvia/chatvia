<?php

use Domain\Analytics\Actions\Recording\RecordMessageSentAction;
use Domain\Analytics\Data\Recording\MessageSentRecordData;
use Domain\Analytics\Events\MessageSent;
use Domain\Analytics\Services\AnalyticsDatabase;
use Domain\Analytics\Services\ResponseTimeHistogram;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * RecordMessageSentAction stores each message as an analytics event and folds
 * it into the conversation metrics and the hourly and daily rollups: tokens,
 * the reply-time histogram, and for an AI reply the answer figures (replies
 * that found no answer, the sum and count of their confidence) the
 * satisfaction numbers read. A conversation's start is also counted under its
 * country, and the channel and device ride along. The queued listener runs
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

    expect($db->table('analytics_events')->where('message_external_id', $event->messageExternalId)->count())->toBe(1)
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

it('adds every message to its day in the daily rollup, the same as the hourly one', function (): void {
    $agentId = 970005;
    $conversation = (string) Str::uuid();

    rmsaRecord(rmsaEvent($agentId, 'user', conversationId: $conversation));
    rmsaRecord(rmsaEvent($agentId, 'assistant', true, 0.8, conversationId: $conversation));

    $db = app(AnalyticsDatabase::class);
    $hourly = $db->table('analytics_agent_hourly_rollups')->where('agent_id', $agentId)->sole();
    $daily = $db->table('analytics_agent_daily')->where('agent_id', $agentId)->sole();

    expect($daily->bucket_day)->toBe('2026-06-01');

    foreach (['conversations_started', 'user_messages', 'assistant_messages', 'total_tokens', 'response_ms_sum', 'response_ms_count', 'response_ms_max', 'confidence_count'] as $column) {
        expect((int) $daily->{$column})->toBe((int) $hourly->{$column}, $column);
    }

    expect((int) $daily->conversations_started)->toBe(1)
        ->and((int) $daily->user_messages)->toBe(1)
        ->and((int) $daily->assistant_messages)->toBe(1);
});

it('keeps each reply time in the histogram and the slowest one', function (): void {
    $agentId = 970006;

    foreach ([300, 500, 4000] as $ms) {
        rmsaRecord(new MessageSent(
            agentId: $agentId,
            workspaceId: 720000,
            chatterId: null,
            conversationExternalId: (string) Str::uuid(),
            messageExternalId: (string) Str::uuid(),
            sessionExternalId: (string) Str::uuid(),
            role: 'assistant',
            llmId: null,
            countryCode: null,
            responseTimeMs: $ms,
            promptTokens: null,
            completionTokens: null,
            occurredAt: Carbon::parse('2026-06-01 10:15'),
        ));
    }
    // A visitor message has no reply time: the histogram stays as it was.
    rmsaRecord(rmsaEvent($agentId, 'user'));

    $db = app(AnalyticsDatabase::class);

    foreach (['analytics_agent_hourly_rollups', 'analytics_agent_daily'] as $table) {
        $row = $db->table($table)->where('agent_id', $agentId)->sole();
        $histogram = Str::of($row->response_ms_hist)->trim('{}')->explode(',')->map(intval(...))->all();

        expect($histogram)->toHaveCount(ResponseTimeHistogram::BUCKETS)
            ->and(array_sum($histogram))->toBe(3)
            ->and($histogram[ResponseTimeHistogram::bucketOf(300)])->toBeGreaterThanOrEqual(1)
            ->and($histogram[ResponseTimeHistogram::bucketOf(4000)])->toBe(1)
            ->and((int) $row->response_ms_max)->toBe(4000)
            ->and((int) $row->response_ms_count)->toBe(3);
    }
});

it('counts a conversation once under its country, and none without one', function (): void {
    $agentId = 970007;
    $conversation = (string) Str::uuid();

    rmsaRecord(rmsaEvent($agentId, 'user', conversationId: $conversation));
    rmsaRecord(rmsaEvent($agentId, 'assistant', conversationId: $conversation));

    $unknown = new MessageSent(
        agentId: $agentId,
        workspaceId: 720000,
        chatterId: null,
        conversationExternalId: (string) Str::uuid(),
        messageExternalId: (string) Str::uuid(),
        sessionExternalId: (string) Str::uuid(),
        role: 'user',
        llmId: null,
        countryCode: null,
        responseTimeMs: null,
        promptTokens: null,
        completionTokens: null,
        occurredAt: Carbon::parse('2026-06-01 11:00'),
    );
    rmsaRecord($unknown);

    $rows = app(AnalyticsDatabase::class)->table('analytics_agent_country_daily')->where('agent_id', $agentId)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->country_code)->toBe('DE')
        ->and((int) $rows->first()->conversations_started)->toBe(1);
});

it('stores the channel on the conversation and the device on the first-seen session', function (): void {
    $event = new MessageSent(
        agentId: 970008,
        workspaceId: 720000,
        chatterId: null,
        conversationExternalId: (string) Str::uuid(),
        messageExternalId: (string) Str::uuid(),
        sessionExternalId: (string) Str::uuid(),
        role: 'user',
        llmId: null,
        countryCode: null,
        responseTimeMs: null,
        promptTokens: null,
        completionTokens: null,
        occurredAt: Carbon::parse('2026-06-01 10:15'),
        source: 'widget',
        deviceType: 'mobile',
        browser: 'Safari',
        os: 'iOS',
    );

    rmsaRecord($event);

    $db = app(AnalyticsDatabase::class);
    $session = $db->table('analytics_session_firsts')->where('session_external_id', $event->sessionExternalId)->sole();

    expect($db->table('analytics_conversation_metrics')->where('conversation_external_id', $event->conversationExternalId)->value('source'))->toBe('widget')
        ->and([$session->device_type, $session->browser, $session->os])->toBe(['mobile', 'Safari', 'iOS']);
});
