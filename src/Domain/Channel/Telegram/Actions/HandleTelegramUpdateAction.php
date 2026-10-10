<?php

declare(strict_types=1);

namespace Domain\Channel\Telegram\Actions;

use Domain\Channel\Actions\HandleChannelMessageAction;
use Domain\Channel\Services\ChannelAccessGate;
use Domain\Channel\Services\Replies\TypingReplyStrategy;
use Domain\Channel\Telegram\Jobs\HandleTelegramUpdateJob;
use Domain\Channel\Telegram\Services\TelegramAdapter;
use Domain\Channel\Telegram\Services\TelegramCommandOutcome;
use Domain\Channel\Telegram\Services\TelegramCommandRouter;

/**
 * Handles one inbound Telegram text message end to end. The same turn as
 * {@see HandleChannelMessageAction}, plus Telegram's slash commands: once the
 * chat's Session + Conversation are resolved, a command may answer with a
 * canned message (no LLM, nothing counted) or swap the text for its prompt.
 * Invoked off the webhook via {@see HandleTelegramUpdateJob}.
 *
 * Messaging channels are for PUBLIC agents only ({@see ChannelAccessGate}).
 */
class HandleTelegramUpdateAction
{
    public function __construct(
        private readonly ChannelAccessGate $gate,
        private readonly TypingReplyStrategy $replies,
        private readonly TelegramCommandRouter $commands,
    ) {}

    public function execute(TelegramAdapter $channel, string $text): void
    {
        if (! $this->gate->allows($channel)) {
            return;
        }

        $chat = $channel->resolveConversation();

        // A slash command resolves to either a canned message (sent directly — no
        // LLM, nothing counted) or a prompt to run through the agent. A plain message,
        // or an unknown /command, returns null and runs the pipeline as-is.
        $outcome = $this->commands->handle($channel->bot(), $chat, $text, $channel->sender());

        if ($outcome instanceof TelegramCommandOutcome && ! $outcome->runThroughAgent) {
            $channel->sendMessage($outcome->text);
        } else {
            $this->replies->deliver($channel, $chat, $outcome?->text ?? $text);
        }

        $chat->markReplied();
    }
}
