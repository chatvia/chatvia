<?php

declare(strict_types=1);

namespace Domain\Channel\Teams\Jobs;

use Domain\Channel\Teams\Actions\HandleTeamsActivityAction;
use Domain\Channel\Teams\Data\TeamsActivityData;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Services\TeamsAdapter;
use Domain\Channel\Teams\Services\TeamsChatResolver;
use Domain\Channel\Teams\Services\TeamsClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

/**
 * Carries one inbound Teams message activity from the webhook (which must
 * answer within the connector's 15-second deadline) to
 * {@see HandleTeamsActivityAction} on the queue.
 */
// The chat queue: a reply never waits behind e-mails or embeddings.
#[Queue('messages')]
// The turn bills the owner on execution — never retry it.
#[Tries(1)]
// A reply may call the model and the tools several times.
#[Timeout(300)]
// A bot disconnected before the job runs has no one to reply to.
#[DeleteWhenMissingModels]
class HandleTeamsActivityJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly AgentTeamsBot $bot,
        public readonly TeamsActivityData $activity,
    ) {}

    public function handle(HandleTeamsActivityAction $action, TeamsClient $client, TeamsChatResolver $resolver): void
    {
        $action->execute(new TeamsAdapter($client, $resolver, $this->bot, $this->activity));
    }
}
