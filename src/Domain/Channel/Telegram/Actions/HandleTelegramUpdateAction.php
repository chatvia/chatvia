<?php

namespace Domain\Channel\Telegram\Actions;

use Domain\Channel\Telegram\Jobs\HandleTelegramUpdateJob;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Services\TelegramChatResolver;
use Domain\Channel\Telegram\Services\TelegramClient;
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
    ) {}

    /**
     * @param  array{first_name?: string|null, username?: string|null}  $from
     */
    public function execute(AgentTelegramBot $bot, string $chatId, string $text, array $from = []): void
    {
        // Protected agents are never answered on Telegram (defence in depth).
        if (! $bot->agent->is_public) {
            $this->client->sendMessage($bot->bot_token, $chatId, self::UNAVAILABLE);

            return;
        }

        $chat = $this->resolver->resolve($bot, $chatId, $from);

        // Instant feedback: "typing…" the moment the message lands. The runner
        // keeps it alive while the agent (RAG + LLM) generates the reply.
        $this->client->sendChatAction($bot->bot_token, $chatId);

        $reply = $this->runner->reply($chat, $text);

        $this->client->sendMessage($bot->bot_token, $chatId, $reply);

        $chat->forceFill(['last_message_at' => now()])->save();
    }
}
