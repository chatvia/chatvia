<?php

namespace Domain\Channel\Whatsapp\Services;

use Domain\Channel\Services\ChannelReplyRunner;
use Domain\Channel\Whatsapp\Models\WhatsappChat;

/**
 * Runs one WhatsApp turn through the real chat pipeline (see
 * {@see ChannelReplyRunner}) and collects the **full reply as a string** —
 * WhatsApp messages can't be edited into a stream, so the only
 * mid-generation feedback is keeping the "typing…" indicator alive while the
 * agent works. The indicator hangs off the inbound message id (wamid), which
 * is why callers pass it through.
 */
class WhatsappReplyRunner
{
    /** Re-send the typing indicator at most this often (WhatsApp clears it after ~25s). */
    private const TYPING_REFRESH_SECONDS = 20.0;

    public function __construct(
        private readonly ChannelReplyRunner $runner,
        private readonly WhatsappClient $client,
    ) {}

    public function reply(WhatsappChat $chat, string $message, string $inboundMessageId): string
    {
        $number = $chat->number;
        $token = $number->access_token;
        $lastTyping = microtime(true);

        return $this->runner->reply(
            agent: $number->agent,
            sessionExternalId: $chat->session_external_id,
            conversationExternalId: $chat->conversation_external_id,
            message: $message,
            source: 'whatsapp',
            requestIdPrefix: 'wa_',
            onEvent: function () use ($token, $number, $chat, $inboundMessageId, &$lastTyping): void {
                if (microtime(true) - $lastTyping >= self::TYPING_REFRESH_SECONDS) {
                    $this->client->sendTypingIndicator($token, $number->phone_number_id, $inboundMessageId);
                    $lastTyping = microtime(true);
                }
            },
            logContext: ['wa_id' => $chat->wa_id],
        );
    }
}
