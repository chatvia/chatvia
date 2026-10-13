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

/**
 * Carries one inbound Slack message from the webhook (which must answer
 * within 3 seconds) to {@see HandleSlackEventAction} on the queue.
 */
class HandleSlackEventJob implements ShouldQueue
{
    use Queueable;

    /** The turn bills the owner on execution — never retry it. */
    public int $tries = 1;

    /** A reply may call the model and the tools several times. */
    public int $timeout = 300;

    public function __construct(
        public readonly AgentSlackApp $app,
        public readonly string $channelId,
        public readonly string $text,
        public readonly ?string $threadTs = null,
    ) {
        // The chat queue: a reply never waits behind e-mails or embeddings.
        $this->onQueue('messages');
    }

    public function handle(HandleSlackEventAction $action, SlackClient $client, SlackChannelResolver $resolver): void
    {
        $action->execute(
            new SlackAdapter($client, $resolver, $this->app, $this->channelId, $this->threadTs),
            $this->text,
        );
    }
}
