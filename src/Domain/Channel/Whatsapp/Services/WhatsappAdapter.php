<?php

namespace Domain\Channel\Whatsapp\Services;

use Domain\Agent\Models\Agent;
use Domain\Channel\Contracts\SendsTypingIndicator;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Domain\Channel\Whatsapp\Models\WhatsappChat;
use Domain\Conversation\Enums\ConversationSource;

/**
 * The WhatsApp side of a channel turn: one business number talking to one
 * WhatsApp user (wa_id). WhatsApp messages can't be edited, so the reply is
 * sent whole while the "typing…" indicator (cleared by WhatsApp after ~25s) is
 * kept alive. The indicator hangs off the inbound message id (wamid), which is
 * why the adapter carries it; the sender's profile name arrives with the
 * webhook and titles a new conversation.
 */
final readonly class WhatsappAdapter implements SendsTypingIndicator
{
    public function __construct(
        private WhatsappClient $client,
        private WhatsappChatResolver $resolver,
        private AgentWhatsappNumber $number,
        private string $waId,
        private string $inboundMessageId,
        private ?string $senderName = null,
    ) {}

    public function agent(): Agent
    {
        return $this->number->agent;
    }

    public function source(): ConversationSource
    {
        return ConversationSource::Whatsapp;
    }

    public function displayName(): string
    {
        return 'WhatsApp';
    }

    public function resolveConversation(): WhatsappChat
    {
        return $this->resolver->resolve($this->number, $this->waId, $this->senderName);
    }

    public function sendMessage(string $markdown): void
    {
        $this->client->sendMessage($this->number->access_token, $this->number->phone_number_id, $this->waId, $markdown);
    }

    /** Marks the inbound message read and shows "typing…" in one call. */
    public function sendTyping(): void
    {
        $this->client->sendTypingIndicator($this->number->access_token, $this->number->phone_number_id, $this->inboundMessageId);
    }

    public function typingRefreshSeconds(): float
    {
        return 20.0;
    }

    public function requestIdPrefix(): string
    {
        return 'wa_';
    }

    public function logContext(): array
    {
        return ['wa_id' => $this->waId];
    }
}
