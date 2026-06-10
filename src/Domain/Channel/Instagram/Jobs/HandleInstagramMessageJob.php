<?php

namespace Domain\Channel\Instagram\Jobs;

use Domain\Channel\Instagram\Actions\HandleInstagramMessageAction;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Carries one inbound Instagram Direct message from the webhook (which must
 * answer quickly or Meta retries and eventually disables the subscription) to
 * {@see HandleInstagramMessageAction} on the queue.
 */
class HandleInstagramMessageJob implements ShouldQueue
{
    use Queueable;

    /** The turn bills the owner on execution — never retry it. */
    public int $tries = 1;

    public function __construct(
        public readonly AgentInstagramAccount $account,
        public readonly string $igsid,
        public readonly string $text,
    ) {}

    public function handle(HandleInstagramMessageAction $action): void
    {
        $action->execute($this->account, $this->igsid, $this->text);
    }
}
