<?php

use Domain\Channel\Messenger\Actions\ReceiveMessengerMessagesAction;
use Domain\Channel\Messenger\Data\MessengerInboundMessageData;
use Domain\Channel\Messenger\Jobs\HandleMessengerMessageJob;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Illuminate\Support\Facades\Bus;

/**
 * MessengerWebhookTest covers routing, the Page's own messages and a repeated
 * delivery through the webhook. This covers what one Page cannot show: the
 * memory of queued messages is kept per Page, so two Pages that report the
 * same message id are each answered, and each only once.
 */
it('remembers queued messages per Page', function (): void {
    Bus::fake();
    $first = AgentMessengerPage::factory()->create(['page_id' => 'PAGE-1']);
    $second = AgentMessengerPage::factory()->create(['page_id' => 'PAGE-2']);
    $messages = [
        new MessengerInboundMessageData('PAGE-1', 'USER-1', 'hello', 'mid.1'),
        new MessengerInboundMessageData('PAGE-2', 'USER-2', 'hi', 'mid.1'),
    ];
    $action = app(ReceiveMessengerMessagesAction::class);

    $action->execute($messages);
    $action->execute($messages);

    Bus::assertDispatchedTimes(HandleMessengerMessageJob::class, 2);
    Bus::assertDispatched(HandleMessengerMessageJob::class, fn (HandleMessengerMessageJob $job): bool => $job->page->is($first) && $job->psid === 'USER-1' && $job->text === 'hello');
    Bus::assertDispatched(HandleMessengerMessageJob::class, fn (HandleMessengerMessageJob $job): bool => $job->page->is($second) && $job->psid === 'USER-2' && $job->text === 'hi');
});
