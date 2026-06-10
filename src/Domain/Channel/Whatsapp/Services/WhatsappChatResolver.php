<?php

namespace Domain\Channel\Whatsapp\Services;

use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Domain\Channel\Whatsapp\Models\WhatsappChat;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatMemory;

/**
 * Resolves the Chatvia Session + Conversation that back a WhatsApp chat,
 * creating them on first contact and reusing them thereafter — so a WhatsApp
 * user keeps one ongoing conversation (and its history) across messages.
 * Unlike Slack/Messenger no lookup call is needed: the user's profile name
 * arrives free in the webhook payload (`contacts[].profile.name`).
 */
class WhatsappChatResolver
{
    public function __construct(
        private readonly ChatMemory $memory,
    ) {}

    public function resolve(AgentWhatsappNumber $number, string $waId, ?string $name = null): WhatsappChat
    {
        $existing = WhatsappChat::query()
            ->where('agent_whatsapp_number_id', $number->id)
            ->where('wa_id', $waId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $agent = $number->agent;

        $session = Session::create([
            'agent_id' => $agent->id,
            'user_id' => null,
            'name' => $name,
        ]);

        $conversationId = $this->memory
            ->withContext($agent, $session->external_id)
            ->createConversation(
                title: $name !== null ? "WhatsApp · {$name}" : 'WhatsApp',
                source: ConversationSource::Whatsapp,
            );

        return WhatsappChat::create([
            'agent_whatsapp_number_id' => $number->id,
            'wa_id' => $waId,
            'session_external_id' => $session->external_id,
            'conversation_external_id' => $conversationId,
            'name' => $name,
            'last_message_at' => now(),
        ]);
    }
}
