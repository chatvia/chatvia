<?php

use Domain\Analytics\Events\FeedbackGiven;
use Domain\Analytics\Jobs\AnalyzeConversationJob;
use Domain\Analytics\Jobs\AnalyzeMessageJob;
use Domain\Analytics\Listeners\RecordAnalytics;
use Domain\Conversation\Models\Conversation;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Queue;

it('puts the analysis jobs on the configured analytics queue', function (): void {
    config(['chatvia.analytics.queue' => 'insights']);

    expect((new AnalyzeMessageJob('msg-1'))->queue)->toBe('insights')
        ->and((new AnalyzeConversationJob(new Conversation))->queue)->toBe('insights');
});

it('retries a failed analytics write with a growing pause', function (): void {
    Queue::fake();

    FeedbackGiven::dispatch(1, 1, null, 'conversation-1', 'message-1', 'positive', null, null, now());

    Queue::assertPushed(
        CallQueuedListener::class,
        fn (CallQueuedListener $job): bool => $job->class === RecordAnalytics::class
            && $job->tries === 5
            && $job->backoff === [5, 30, 120],
    );
});
