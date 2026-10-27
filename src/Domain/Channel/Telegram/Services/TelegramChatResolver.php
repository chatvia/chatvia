<?php

namespace Domain\Channel\Telegram\Services;

use Domain\Channel\Services\ChannelConversationResolver;
use Domain\Channel\Telegram\Data\TelegramSenderData;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Models\TelegramChat;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\ConversationSource;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the Chatvia Session + Conversation that back a Telegram chat, creating
 * them on first contact and reusing them thereafter — so a Telegram user keeps
 * one ongoing conversation (and its history) across messages.
 *
 * @extends ChannelConversationResolver<TelegramChat>
 */
class TelegramChatResolver extends ChannelConversationResolver
{
    public function resolve(AgentTelegramBot $bot, int|string $chatId, TelegramSenderData $sender = new TelegramSenderData): TelegramChat
    {
        $firstName = $sender->firstName;

        return $this->resolveMapping(
            lookup: TelegramChat::query()
                ->where('agent_telegram_bot_id', $bot->id)
                ->where('chat_id', (string) $chatId),
            agent: $bot->agent,
            identity: [
                'agent_telegram_bot_id' => $bot->id,
                'chat_id' => (string) $chatId,
            ],
            source: ConversationSource::Telegram,
            context: fn (): array => [
                'name' => $firstName,
                'title' => $firstName !== null ? "Telegram · {$firstName}" : 'Telegram',
                'attributes' => [
                    'first_name' => $firstName,
                    'username' => $sender->username,
                ],
            ],
        );
    }

    /**
     * Rotate this chat onto a fresh Conversation under the same Session — a /reset
     * gives the user a clean thread while keeping their persistent identity (and
     * the previous conversation's history intact for the dashboard).
     */
    public function startNewConversation(TelegramChat $chat): void
    {
        $title = $chat->first_name !== null ? "Telegram · {$chat->first_name}" : 'Telegram';

        $conversationId = $this->startConversation->execute(
            new ChatScopeData($chat->bot->agent, $chat->session->external_id),
            ConversationSource::Telegram,
            $title,
        )->external_id;

        $conversation = $chat->session->conversations()->where('external_id', $conversationId)->firstOrFail();

        $chat->update(['chat_conversation_id' => $conversation->id]);

        // Point the relation at the new thread so a prompt-mode /reset runs the
        // pipeline against the fresh conversation, not the stale cached one.
        $chat->setRelation('conversation', $conversation);
    }

    /**
     * @return TelegramChat
     */
    protected function createMapping(array $attributes): Model
    {
        return TelegramChat::query()->create($attributes);
    }
}
