<?php

declare(strict_types=1);

namespace Domain\Channel\Instagram\Jobs;

use Domain\Channel\Actions\HandleChannelMessageAction;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Instagram\Services\InstagramAdapter;
use Domain\Channel\Instagram\Services\InstagramClient;
use Domain\Channel\Instagram\Services\InstagramThreadResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Carries one inbound Instagram Direct message from the webhook (which must
 * answer quickly or Meta retries and eventually disables the subscription) to
 * {@see HandleChannelMessageAction} (as an {@see InstagramAdapter}) on the queue.
 */
class HandleInstagramMessageJob implements ShouldQueue
{
    use Queueable;

    /** The turn bills the owner on execution — never retry it. */
    public int $tries = 1;

    /** A reply may call the model and the tools several times. */
    public int $timeout = 300;

    public function __construct(
        public readonly AgentInstagramAccount $account,
        public readonly string $igsid,
        public readonly string $text,
    ) {
        // The chat queue: a reply never waits behind e-mails or embeddings.
        $this->onQueue('messages');
    }

    public function handle(HandleChannelMessageAction $action, InstagramClient $client, InstagramThreadResolver $resolver): void
    {
        $action->execute(new InstagramAdapter($client, $resolver, $this->account, $this->igsid), $this->text);
    }
}
