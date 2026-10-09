<?php

declare(strict_types=1);

namespace Domain\Channel\Teams\Actions;

use Domain\Channel\Actions\HandleChannelMessageAction;
use Domain\Channel\Teams\Jobs\HandleTeamsActivityJob;
use Domain\Channel\Teams\Services\TeamsAdapter;

/**
 * Handles one inbound Teams message activity: run the shared channel turn
 * ({@see HandleChannelMessageAction}) on the activity's text (the bot's own
 * mention already removed) — the reply streams into a placeholder activity. An
 * activity that was only the mention is ignored. Invoked off the webhook via
 * {@see HandleTeamsActivityJob}.
 */
class HandleTeamsActivityAction
{
    public function __construct(
        private readonly HandleChannelMessageAction $handle,
    ) {}

    public function execute(TeamsAdapter $channel): void
    {
        $text = $channel->activity()->text;

        if ($text === '') {
            return;
        }

        $this->handle->execute($channel, $text);
    }
}
