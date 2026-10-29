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
 * country, and the chatter, channel and device ride along. The queued
 * listener runs on an at-least-once queue, so a redelivered message must
 * change nothing.
 */
function rmsaEvent(
    int $agentId,
    string $role = 'assistant',
    ?bool $found = null,
    ?float $confidence = null,
    ?int $chatterId = null,
    string $conversationId = '00000000-0000-4000-8000-000000000720',
    ?int $responseMs = 500,
    ?string $country = 'DE',
): MessageSent {
    return new MessageSent(
        agentId: $agentId,
        workspaceId: 720000,
        chatterId: $chatterId,
        conversationExternalId: $conversationId,
        messageExternalId: (string) Str::uuid7(),
        sessionExternalId: '00000000-0000-4000-9000-000000000720',
        role: $role,
        llmId: 7,
        countryCode: $country,
        responseTimeMs: $role === 'assistant' ? $responseMs : null,
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

it('folds the answer figures, reply times and country into matching hourly and daily rollups', function (): void {
    $agentId = 970001;
    $conversation = (string) Str::uuid7();

    rmsaRecord(rmsaEvent($agentId, 'user', false, 0.5, conversationId: $conversation));
    rmsaRecord(rmsaEvent($agentId, 'assistant', true, 0.9, conversationId: $conversation, responseMs: 300));
    rmsaRecord(rmsaEvent($agentId, 'assistant', false, 0.4, conversationId: $conversation, responseMs: 500));
    rmsaRecord(rmsaEvent($agentId, conversationId: $conversation, responseMs: 4000));
    rmsaRecord(rmsaEvent($agentId, 'user', conversationId: (string) Str::uuid7(), country: null));

    $db = app(AnalyticsDatabase::class);
    $hourly = $db->table('analytics_agent_hourly_rollups')->where('agent_id', $agentId)->sole();
    $daily = $db->table('analytics_agent_daily')->where('agent_id', $agentId)->sole();
    $countries = $db->table('analytics_agent_country_daily')->where('agent_id', $agentId)->get();

    expect((int) $hourly->assistant_messages)->toBe(3)
        ->and((int) $hourly->user_messages)->toBe(2)
        ->and((int) $hourly->unanswered_replies)->toBe(1)
        ->and((float) $hourly->confidence_sum)->toBe(1.3)
        ->and((int) $hourly->confidence_count)->toBe(2)
        ->and($daily->bucket_day)->toBe('2026-06-01')
        ->and($countries)->toHaveCount(1)
        ->and($countries->first()->country_code)->toBe('DE')
        ->and((int) $countries->first()->conversations_started)->toBe(1);

    foreach (['conversations_started', 'user_messages', 'assistant_messages', 'total_tokens', 'response_ms_sum', 'confidence_count'] as $column) {
        expect((int) $daily->{$column})->toBe((int) $hourly->{$column}, $column);
    }

    foreach ([$hourly, $daily] as $row) {
        $histogram = Str::of($row->response_ms_hist)->trim('{}')->explode(',')->map(intval(...))->all();

        expect($histogram)->toHaveCount(ResponseTimeHistogram::BUCKETS)
            ->and(array_sum($histogram))->toBe(3)
            ->and($histogram[ResponseTimeHistogram::bucketOf(300)])->toBeGreaterThanOrEqual(1)
            ->and($histogram[ResponseTimeHistogram::bucketOf(4000)])->toBe(1)
            ->and((int) $row->response_ms_max)->toBe(4000)
            ->and((int) $row->response_ms_count)->toBe(3);
    }
});

it('records a redelivered message exactly once across events, metrics and rollups', function (): void {
    $agentId = 970003;
    $conversationId = (string) Str::uuid7();
    $event = rmsaEvent($agentId, conversationId: $conversationId);

    rmsaRecord($event);
    rmsaRecord($event);

    $db = app(AnalyticsDatabase::class);
    $rollup = $db->table('analytics_agent_hourly_rollups')->where('agent_id', $agentId)->sole();

    expect($db->table('analytics_events')->where('message_external_id', $event->messageExternalId)->count())->toBe(1)
        ->and($db->table('analytics_conversation_metrics')->where('conversation_external_id', $conversationId)->value('message_count'))->toBe(1)
        ->and((int) $rollup->assistant_messages)->toBe(1)
        ->and((int) $rollup->prompt_tokens)->toBe(100)
        ->and((int) $rollup->completion_tokens)->toBe(50)
        ->and((int) $rollup->total_tokens)->toBe(150);
});

it('stores the chatter, the channel and the first-seen device of a message', function (): void {
    $anonymous = rmsaEvent(970008);
    $event = new MessageSent(
        agentId: 970008,
        workspaceId: 720000,
        chatterId: 970108,
        conversationExternalId: (string) Str::uuid7(),
        messageExternalId: (string) Str::uuid7(),
        sessionExternalId: (string) Str::uuid7(),
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
    rmsaRecord($anonymous);

    $db = app(AnalyticsDatabase::class);
    $chatterOf = fn (MessageSent $sent): mixed => $db->table('analytics_events')->where('message_external_id', $sent->messageExternalId)->value('chatter_id');
    $session = $db->table('analytics_session_firsts')->where('session_external_id', $event->sessionExternalId)->sole();

    expect($chatterOf($event))->toBe(970108)
        ->and($chatterOf($anonymous))->toBeNull()
        ->and($db->table('analytics_conversation_metrics')->where('conversation_external_id', $event->conversationExternalId)->value('source'))->toBe('widget')
        ->and([$session->device_type, $session->browser, $session->os])->toBe(['mobile', 'Safari', 'iOS']);
});
