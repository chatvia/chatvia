<?php

namespace Domain\Channel\Whatsapp\Actions;

use Domain\Channel\Services\ChannelAccessGate;
use Domain\Channel\Whatsapp\Jobs\HandleWhatsappMessageJob;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Domain\Channel\Whatsapp\Services\WhatsappChatResolver;
use Domain\Channel\Whatsapp\Services\WhatsappClient;
use Domain\Channel\Whatsapp\Services\WhatsappReplyRunner;

/**
 * Handles one inbound WhatsApp text message end to end: resolve the chat's
 * Session + Conversation, run the agent through the real pipeline to get a
 * reply, and send it back. Invoked off the webhook via
 * {@see HandleWhatsappMessageJob}.
 *
 * Messaging channels are for PUBLIC agents only: a WhatsApp sender is
 * anonymous to Chatvia and can't satisfy a protected agent's access model.
 * Connecting is blocked for protected agents at the controller; this is the
 * runtime backstop for an agent flipped to protected after it was already
 * connected.
 */
class HandleWhatsappMessageAction
{
    private const UNAVAILABLE = 'This assistant isn’t available on WhatsApp right now.';

    public function __construct(
        private readonly WhatsappChatResolver $resolver,
        private readonly WhatsappReplyRunner $runner,
        private readonly WhatsappClient $client,
        private readonly ChannelAccessGate $gate,
    ) {}

    public function execute(AgentWhatsappNumber $number, string $waId, string $text, string $messageId, ?string $name = null): void
    {
        // Protected agents are never answered on WhatsApp (defence in depth).
        if (! $this->gate->allows($number->agent, fn () => $this->client->sendMessage($number->access_token, $number->phone_number_id, $waId, self::UNAVAILABLE))) {
            return;
        }

        $chat = $this->resolver->resolve($number, $waId, $name);

        // Instant feedback: mark the message read + "typing…" the moment it
        // lands. The runner keeps the indicator alive while the agent (RAG +
        // LLM) generates the reply.
        $this->client->sendTypingIndicator($number->access_token, $number->phone_number_id, $messageId);

        $reply = $this->runner->reply($chat, $text, $messageId);

        $this->client->sendMessage($number->access_token, $number->phone_number_id, $waId, $reply);

        $chat->forceFill(['last_message_at' => now()])->save();
    }
}
