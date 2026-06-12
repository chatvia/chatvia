<?php

namespace Domain\Channel\Telegram\Services;

use Domain\Channel\Services\ChannelReplyRunner;
use Domain\Channel\Telegram\Models\TelegramChat;

/**
 * Runs one Telegram turn through the real chat pipeline (see
 * {@see ChannelReplyRunner}) and collects the **full reply as a string** —
 * Telegram messages can't be edited into a stream, so the only mid-generation
 * feedback is keeping the "typing…" indicator alive while the agent works.
 */
class TelegramReplyRunner
{
    /** Re-send the typing indicator at most this often (Telegram clears it after ~5s). */
    private const TYPING_REFRESH_SECONDS = 4.0;

    public function __construct(
        private readonly ChannelReplyRunner $runner,
        private readonly TelegramClient $client,
    ) {}

    public function reply(TelegramChat $chat, string $message): string
    {
        $token = $chat->bot->bot_token;
        $lastTyping = microtime(true);

        return $this->runner->reply(
            agent: $chat->bot->agent,
            sessionExternalId: $chat->session->external_id,
            conversationExternalId: $chat->conversation->external_id,
            message: $message,
            source: 'telegram',
            requestIdPrefix: 'tg_',
            onEvent: function () use ($token, $chat, &$lastTyping): void {
                if (microtime(true) - $lastTyping >= self::TYPING_REFRESH_SECONDS) {
                    $this->client->sendChatAction($token, $chat->chat_id);
                    $lastTyping = microtime(true);
                }
            },
            logContext: ['chat_id' => $chat->chat_id],
        );
    }
}
