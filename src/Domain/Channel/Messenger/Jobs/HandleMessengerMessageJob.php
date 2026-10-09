<?php

namespace Domain\Channel\Messenger\Jobs;

use Domain\Channel\Actions\HandleChannelMessageAction;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Services\MessengerAdapter;
use Domain\Channel\Messenger\Services\MessengerClient;
use Domain\Channel\Messenger\Services\MessengerThreadResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Carries one inbound Messenger message from the webhook (which must answer
 * quickly or Meta retries and eventually disables the subscription) to
 * {@see HandleChannelMessageAction} (as a {@see MessengerAdapter}) on the queue.
 */
class HandleMessengerMessageJob implements ShouldQueue
{
    use Queueable;

    /** The turn bills the owner on execution — never retry it. */
    public int $tries = 1;

    public function __construct(
        public readonly AgentMessengerPage $page,
        public readonly string $psid,
        public readonly string $text,
    ) {}

    public function handle(HandleChannelMessageAction $action, MessengerClient $client, MessengerThreadResolver $resolver): void
    {
        $action->execute(new MessengerAdapter($client, $resolver, $this->page, $this->psid), $this->text);
    }
}
