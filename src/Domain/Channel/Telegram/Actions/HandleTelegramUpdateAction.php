<?php

namespace Domain\Channel\Telegram\Actions;

use Domain\Channel\Services\ChannelAccessGate;
use Domain\Channel\Telegram\Jobs\HandleTelegramUpdateJob;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Services\TelegramChatResolver;
use Domain\Channel\Telegram\Services\TelegramClient;
use Domain\Channel\Telegram\Services\TelegramCommandRouter;
use Domain\Channel\Telegram\Services\TelegramReplyRunner;

/**
 * Handles one inbound Telegram text message end to end: resolve the chat's
 * Session + Conversation, run the agent through the real pipeline to get a reply,
 * and send it back. Invoked off the webhook via {@see HandleTelegramUpdateJob}.
 *
 * Messaging channels are for PUBLIC agents only: a Telegram sender is anonymous
 * and can't satisfy a protected agent's access model. Connecting is blocked for
 * protected agents at the controller; this is the runtime backstop for an agent
 * flipped to protected after it was already connected.
 */
class HandleTelegramUpdateAction
{
    private const UNAVAILABLE = 'This assistant isn’t available on Telegram right now.';

    public function __construct(
        private readonly TelegramChatResolver $resolver,
        private readonly TelegramReplyRunner $runner,
        private readonly TelegramClient $client,
        private readonly ChannelAccessGate $gate,
        private readonly TelegramCommandRouter $commands,
    ) {}

    /**
     * @param  array{first_name?: string|null, username?: string|null}  $from
     */
    public function execute(AgentTelegramBot $bot, string $chatId, string $text, array $from = []): void
    {
        // Protected agents are never answered on Telegram (defence in depth).
        if (! $this->gate->allows($bot->agent, fn () => $this->client->sendMessage($bot->bot_token, $chatId, self::UNAVAILABLE))) {
            return;
        }

        $chat = $this->resolver->resolve($bot, $chatId, $from);

        // A slash command resolves to either a canned message (sent directly — no
        // LLM, no billing) or a prompt to run through the agent. A plain message,
        // or an unknown /command, returns null and runs the pipeline as-is.
        $outcome = $this->commands->handle($bot, $chat, $text, $from);

        if ($outcome !== null && ! $outcome->runThroughAgent) {
            $this->client->sendMessage($bot->bot_token, $chatId, $outcome->text);
            $chat->forceFill(['last_message_at' => now()])->save();

            return;
        }

        // Either a prompt-mode command (run its prompt) or a normal turn (the
        // original text). Instant "typing…" while the agent (RAG + LLM) works.
        $this->client->sendChatAction($bot->bot_token, $chatId);

        $reply = $this->runner->reply($chat, $outcome?->text ?? $text);

        $this->client->sendMessage($bot->bot_token, $chatId, $reply);

        $chat->forceFill(['last_message_at' => now()])->save();
    }
}
