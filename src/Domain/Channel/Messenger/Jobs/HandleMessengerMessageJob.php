<?php

declare(strict_types=1);

namespace Domain\Channel\Messenger\Jobs;

use Domain\Channel\Actions\HandleChannelMessageAction;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Services\MessengerAdapter;
use Domain\Channel\Messenger\Services\MessengerClient;
use Domain\Channel\Messenger\Services\MessengerThreadResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

/**
 * Carries one inbound Messenger message from the webhook (which must answer
 * quickly or Meta retries and eventually disables the subscription) to
 * {@see HandleChannelMessageAction} (as a {@see MessengerAdapter}) on the queue.
 */
// The chat queue: a reply never waits behind e-mails or embeddings.
#[Queue('messages')]
// The turn bills the owner on execution — never retry it.
#[Tries(1)]
// A reply may call the model and the tools several times.
#[Timeout(300)]
// A page disconnected before the job runs has no one to reply to.
#[DeleteWhenMissingModels]
class HandleMessengerMessageJob implements ShouldQueue
{
    use Queueable;

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
