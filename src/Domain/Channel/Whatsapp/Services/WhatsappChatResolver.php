<?php

namespace Domain\Channel\Whatsapp\Services;

use Domain\Channel\Services\ChannelConversationResolver;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Domain\Channel\Whatsapp\Models\WhatsappChat;
use Domain\Conversation\Enums\ConversationSource;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the Chatvia Session + Conversation that back a WhatsApp chat,
 * creating them on first contact and reusing them thereafter — so a WhatsApp
 * user keeps one ongoing conversation (and its history) across messages.
 * Unlike Slack/Messenger no lookup call is needed: the user's profile name
 * arrives free in the webhook payload (`contacts[].profile.name`).
 *
 * @extends ChannelConversationResolver<WhatsappChat>
 */
class WhatsappChatResolver extends ChannelConversationResolver
{
    public function resolve(AgentWhatsappNumber $number, string $waId, ?string $name = null): WhatsappChat
    {
        return $this->resolveMapping(
            lookup: WhatsappChat::query()
                ->where('agent_whatsapp_number_id', $number->id)
                ->where('wa_id', $waId),
            agent: $number->agent,
            identity: [
                'agent_whatsapp_number_id' => $number->id,
                'wa_id' => $waId,
            ],
            source: ConversationSource::Whatsapp,
            context: fn (): array => [
                'name' => $name,
                'title' => $name !== null ? "WhatsApp · {$name}" : 'WhatsApp',
                'attributes' => ['name' => $name],
            ],
        );
    }

    /**
     * @return WhatsappChat
     */
    protected function createMapping(array $attributes): Model
    {
        return WhatsappChat::create($attributes);
    }
}
