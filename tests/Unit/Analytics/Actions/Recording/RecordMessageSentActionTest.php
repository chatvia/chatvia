<?php

use Domain\Analytics\Actions\Recording\RecordMessageSentAction;
use Domain\Analytics\Data\Recording\MessageSentRecordData;
use Domain\Analytics\Events\MessageSent;
use Domain\Analytics\Services\AnalyticsDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * RecordMessageSentAction folds each AI reply's answer figures into the hourly
 * rollup: replies that found no answer, and the sum and count of the replies'
 * confidence. The satisfaction numbers read them from there.
 */
function rmsaEvent(int $agentId, string $role, ?bool $found, ?float $confidence): MessageSent
{
    return new MessageSent(
        agentId: $agentId,
        workspaceId: 720000,
        chatterId: null,
        conversationExternalId: '00000000-0000-4000-8000-000000000720',
        messageExternalId: (string) Str::uuid(),
        sessionExternalId: '00000000-0000-4000-9000-000000000720',
        role: $role,
        llmId: 7,
        countryCode: 'DE',
        responseTimeMs: $role === 'assistant' ? 500 : null,
        promptTokens: null,
        completionTokens: null,
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

    rmsaRecord(rmsaEvent($agentId, 'user', true, null));
    rmsaRecord(rmsaEvent($agentId, 'assistant', true, 0.9));
    rmsaRecord(rmsaEvent($agentId, 'assistant', false, 0.4));
    // Could not be told, and no confidence: counted as a reply only.
    rmsaRecord(rmsaEvent($agentId, 'assistant', null, null));

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
