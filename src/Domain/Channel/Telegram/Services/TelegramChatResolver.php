<?php

namespace Domain\Channel\Telegram\Services;

use Domain\Channel\Services\ChannelConversationResolver;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Models\TelegramChat;
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
    /**
     * @param  array{first_name?: string|null, username?: string|null}  $from
     */
    public function resolve(AgentTelegramBot $bot, int|string $chatId, array $from = []): TelegramChat
    {
        $firstName = $from['first_name'] ?? null;

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
                    'username' => $from['username'] ?? null,
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

        $this->memory
            ->withContext($chat->bot->agent, $chat->session->external_id)
            ->createConversation(title: $title, source: ConversationSource::Telegram);

        // The conversation createConversation() just made is the newest for the session.
        $conversation = $chat->session->conversations()->latest('id')->firstOrFail();

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
        return TelegramChat::create($attributes);
    }
}
