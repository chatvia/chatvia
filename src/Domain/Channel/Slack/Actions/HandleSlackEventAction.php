<?php

declare(strict_types=1);

namespace Domain\Channel\Slack\Actions;

use Domain\Channel\Actions\HandleChannelMessageAction;
use Domain\Channel\Slack\Jobs\HandleSlackEventJob;
use Domain\Channel\Slack\Services\SlackAdapter;

/**
 * Handles one inbound Slack message: drop the bot's own @mention, then run
 * the shared channel turn ({@see HandleChannelMessageAction}) — the reply
 * streams into a placeholder message. A message that was only the mention is
 * ignored. Invoked off the webhook via {@see HandleSlackEventJob}.
 */
class HandleSlackEventAction
{
    public function __construct(
        private readonly HandleChannelMessageAction $handle,
    ) {}

    public function execute(SlackAdapter $channel, string $text): void
    {
        $text = $channel->withoutBotMention($text);

        if ($text === '') {
            return;
        }

        $this->handle->execute($channel, $text);
    }
}
