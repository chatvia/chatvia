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
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

/**
 * Carries one inbound Instagram Direct message from the webhook (which must
 * answer quickly or Meta retries and eventually disables the subscription) to
 * {@see HandleChannelMessageAction} (as an {@see InstagramAdapter}) on the queue.
 */
// The chat queue: a reply never waits behind e-mails or embeddings.
#[Queue('messages')]
// The turn bills the owner on execution — never retry it.
#[Tries(1)]
// A reply may call the model and the tools several times.
#[Timeout(300)]
// An account disconnected before the job runs has no one to reply to.
#[DeleteWhenMissingModels]
class HandleInstagramMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly AgentInstagramAccount $account,
        public readonly string $igsid,
        public readonly string $text,
    ) {}

    public function handle(HandleChannelMessageAction $action, InstagramClient $client, InstagramThreadResolver $resolver): void
    {
        $action->execute(new InstagramAdapter($client, $resolver, $this->account, $this->igsid), $this->text);
    }
}
