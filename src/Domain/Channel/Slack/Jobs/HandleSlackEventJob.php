<?php

declare(strict_types=1);

namespace Domain\Channel\Slack\Jobs;

use Domain\Channel\Slack\Actions\HandleSlackEventAction;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Slack\Services\SlackAdapter;
use Domain\Channel\Slack\Services\SlackChannelResolver;
use Domain\Channel\Slack\Services\SlackClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

/**
 * Carries one inbound Slack message from the webhook (which must answer
 * within 3 seconds) to {@see HandleSlackEventAction} on the queue.
 */
// The chat queue: a reply never waits behind e-mails or embeddings.
#[Queue('messages')]
// The turn bills the owner on execution — never retry it.
#[Tries(1)]
// A reply may call the model and the tools several times.
#[Timeout(300)]
// An app disconnected before the job runs has no one to reply to.
#[DeleteWhenMissingModels]
class HandleSlackEventJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly AgentSlackApp $app,
        public readonly string $channelId,
        public readonly string $text,
        public readonly ?string $threadTs = null,
    ) {}

    public function handle(HandleSlackEventAction $action, SlackClient $client, SlackChannelResolver $resolver): void
    {
        $action->execute(
            new SlackAdapter($client, $resolver, $this->app, $this->channelId, $this->threadTs),
            $this->text,
        );
    }
}
