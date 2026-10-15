<?php

use Domain\Analytics\Events\MessageSent;
use Domain\Analytics\Jobs\AnalyzeMessageJob;
use Domain\Analytics\Listeners\QueueMessageAnalysis;
use Illuminate\Support\Facades\Bus;

/**
 * Every stored visitor message is queued for sentiment and tone analysis; AI
 * replies are not.
 */
function qmaMessageSent(string $role): MessageSent
{
    return new MessageSent(1, 2, null, 'conversation', 'message-'.$role, 'session', $role, null, null, null, null, null, now());
}

it('queues the analysis of a visitor message', function (): void {
    Bus::fake();

    new QueueMessageAnalysis()->handle(qmaMessageSent('user'));

    Bus::assertDispatched(AnalyzeMessageJob::class, fn (AnalyzeMessageJob $job): bool => $job->externalMessageId === 'message-user');
});

it('skips an assistant reply', function (): void {
    Bus::fake();

    new QueueMessageAnalysis()->handle(qmaMessageSent('assistant'));

    Bus::assertNotDispatched(AnalyzeMessageJob::class);
});

it('is wired to the message-stored event', function (): void {
    Bus::fake();

    event(qmaMessageSent('user'));

    Bus::assertDispatched(AnalyzeMessageJob::class);
});
