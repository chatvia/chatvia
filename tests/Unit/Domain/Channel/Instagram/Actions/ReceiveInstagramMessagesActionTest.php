<?php

use Domain\Channel\Instagram\Actions\ReceiveInstagramMessagesAction;
use Domain\Channel\Instagram\Data\InstagramInboundMessageData;
use Domain\Channel\Instagram\Jobs\HandleInstagramMessageJob;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Illuminate\Support\Facades\Bus;

/**
 * InstagramWebhookTest covers routing, the account's own messages and a
 * repeated delivery through the webhook. This covers what one account cannot
 * show: the memory of queued messages is kept per account, so two accounts
 * that report the same message id are each answered, and each only once.
 */
it('remembers queued messages per Instagram account', function (): void {
    Bus::fake();
    $first = AgentInstagramAccount::factory()->create(['ig_id' => 'IG-1']);
    $second = AgentInstagramAccount::factory()->create(['ig_id' => 'IG-2']);
    $messages = [
        new InstagramInboundMessageData('IG-1', 'USER-1', 'hello', 'mid.1'),
        new InstagramInboundMessageData('IG-2', 'USER-2', 'hi', 'mid.1'),
    ];
    $action = app(ReceiveInstagramMessagesAction::class);

    $action->execute($messages);
    $action->execute($messages);

    Bus::assertDispatchedTimes(HandleInstagramMessageJob::class, 2);
    Bus::assertDispatched(HandleInstagramMessageJob::class, fn (HandleInstagramMessageJob $job): bool => $job->account->is($first) && $job->igsid === 'USER-1' && $job->text === 'hello');
    Bus::assertDispatched(HandleInstagramMessageJob::class, fn (HandleInstagramMessageJob $job): bool => $job->account->is($second) && $job->igsid === 'USER-2' && $job->text === 'hi');
});
