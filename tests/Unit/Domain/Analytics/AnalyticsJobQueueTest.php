<?php

use Domain\Analytics\Jobs\AnalyzeConversationJob;
use Domain\Analytics\Jobs\AnalyzeMessageJob;
use Domain\Analytics\Listeners\RecordAnalytics;
use Domain\Conversation\Listeners\RecordToolCallAudit;
use Domain\Conversation\Models\Conversation;
use Domain\Guardrail\Listeners\RecordGuardrailAttack;
use Domain\Guardrail\Listeners\RecordGuardrailEvent;
use Domain\Integration\Mcp\Listeners\UpdateMcpInvocationCounters;
use Domain\Security\Listeners\RecordSecurityEvent;

/**
 * The analysis jobs run on the configured analytics queue, apart from the
 * chat queues. The quick database writes (analytics counters, audit rows) run
 * on a records queue of their own, so they never wait behind those.
 */
it('puts the analysis jobs on the configured analytics queue', function (): void {
    config(['chatvia.analytics.queue' => 'insights']);

    expect((new AnalyzeMessageJob('msg-1'))->queue)->toBe('insights')
        ->and((new AnalyzeConversationJob(new Conversation))->queue)->toBe('insights');
});

it('puts the quick database writes on the configured records queue', function (string $listener): void {
    config(['chatvia.analytics.record_queue' => 'writes']);

    expect(app($listener)->viaQueue())->toBe('writes');
})->with([
    RecordAnalytics::class,
    UpdateMcpInvocationCounters::class,
    RecordToolCallAudit::class,
    RecordSecurityEvent::class,
    RecordGuardrailEvent::class,
    RecordGuardrailAttack::class,
]);

it('keeps the slow AI work off the records queue', function (): void {
    expect(config('chatvia.analytics.record_queue'))->not->toBe(config('chatvia.analytics.queue'))
        ->and(config('horizon.defaults.records.queue'))->toBe([config('chatvia.analytics.record_queue')]);
});
