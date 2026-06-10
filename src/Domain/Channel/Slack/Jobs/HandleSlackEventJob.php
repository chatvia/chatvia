<?php

namespace Domain\Channel\Slack\Jobs;

use Domain\Channel\Slack\Actions\HandleSlackEventAction;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Carries one inbound Slack message from the webhook (which must answer
 * within 3 seconds) to {@see HandleSlackEventAction} on the queue.
 */
class HandleSlackEventJob implements ShouldQueue
{
    use Queueable;

    /** The turn bills the owner on execution — never retry it. */
    public int $tries = 1;

    public function __construct(
        public readonly AgentSlackApp $app,
        public readonly string $channelId,
        public readonly string $text,
        public readonly ?string $threadTs = null,
    ) {}

    public function handle(HandleSlackEventAction $action): void
    {
        $action->execute($this->app, $this->channelId, $this->text, $this->threadTs);
    }
}
