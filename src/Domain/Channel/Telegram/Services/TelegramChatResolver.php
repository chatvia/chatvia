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
     * @return TelegramChat
     */
    protected function createMapping(array $attributes): Model
    {
        return TelegramChat::create($attributes);
    }
}
