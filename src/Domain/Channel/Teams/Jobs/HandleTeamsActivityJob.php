<?php

namespace Domain\Channel\Teams\Jobs;

use Domain\Channel\Teams\Actions\HandleTeamsActivityAction;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Carries one inbound Teams message activity from the webhook (which must
 * answer within the connector's 15-second deadline) to
 * {@see HandleTeamsActivityAction} on the queue.
 */
class HandleTeamsActivityJob implements ShouldQueue
{
    use Queueable;

    /** The turn bills the owner on execution — never retry it. */
    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $activity
     */
    public function __construct(
        public readonly AgentTeamsBot $bot,
        public readonly array $activity,
    ) {}

    public function handle(HandleTeamsActivityAction $action): void
    {
        $action->execute($this->bot, $this->activity);
    }
}
