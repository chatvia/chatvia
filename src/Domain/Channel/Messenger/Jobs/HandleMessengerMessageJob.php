<?php

namespace Domain\Channel\Messenger\Jobs;

use Domain\Channel\Messenger\Actions\HandleMessengerMessageAction;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Carries one inbound Messenger message from the webhook (which must answer
 * quickly or Meta retries and eventually disables the subscription) to
 * {@see HandleMessengerMessageAction} on the queue.
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

    public function handle(HandleMessengerMessageAction $action): void
    {
        $action->execute($this->page, $this->psid, $this->text);
    }
}
