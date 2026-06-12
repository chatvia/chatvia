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
            // conversation() excludes soft-deleted rows, so a retention-pruned
            // conversation reads as missing here.
            if ($existing->conversation()->exists()) {
                return $existing;
            }

            // Conversation was retention-pruned — drop the stale mapping and
            // mint a fresh session + conversation below.
            $existing->delete();
        }

        $agent = $number->agent;

        $session = Session::create([
            'agent_id' => $agent->id,
            'user_id' => null,
            'name' => $name,
        ]);

        $this->memory
            ->withContext($agent, $session->external_id)
            ->createConversation(
                title: $name !== null ? "WhatsApp · {$name}" : 'WhatsApp',
                source: ConversationSource::Whatsapp,
            );

        // The freshly-created session has exactly one conversation — the one
        // createConversation() just made for it.
        $conversation = $session->conversations()->latest('id')->firstOrFail();

        return WhatsappChat::create([
            'agent_whatsapp_number_id' => $number->id,
            'wa_id' => $waId,
            'chat_session_id' => $session->id,
            'chat_conversation_id' => $conversation->id,
            'name' => $name,
            'last_message_at' => now(),
        ]);
    }
}
