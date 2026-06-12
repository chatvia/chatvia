<?php

namespace Domain\Channel\Telegram\Services;

use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Models\TelegramChat;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatMemory;

/**
 * Resolves the Chatvia Session + Conversation that back a Telegram chat, creating
 * them on first contact and reusing them thereafter — so a Telegram user keeps
 * one ongoing conversation (and its history) across messages.
 */
class TelegramChatResolver
{
    public function __construct(
        private readonly ChatMemory $memory,
    ) {}

    /**
     * @param  array{first_name?: string|null, username?: string|null}  $from
     */
    public function resolve(AgentTelegramBot $bot, int|string $chatId, array $from = []): TelegramChat
    {
        $existing = TelegramChat::query()
            ->where('agent_telegram_bot_id', $bot->id)
            ->where('chat_id', (string) $chatId)
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

        $agent = $bot->agent;
        $firstName = $from['first_name'] ?? null;

        $session = Session::create([
            'agent_id' => $agent->id,
            'user_id' => null,
            'name' => $firstName,
        ]);

        $this->memory
            ->withContext($agent, $session->external_id)
            ->createConversation(
                title: $firstName !== null ? "Telegram · {$firstName}" : 'Telegram',
                source: ConversationSource::Telegram,
            );

        // The freshly-created session has exactly one conversation — the one
        // createConversation() just made for it.
        $conversation = $session->conversations()->latest('id')->firstOrFail();

        return TelegramChat::create([
            'agent_telegram_bot_id' => $bot->id,
            'chat_id' => (string) $chatId,
            'chat_session_id' => $session->id,
            'chat_conversation_id' => $conversation->id,
            'first_name' => $firstName,
            'username' => $from['username'] ?? null,
            'last_message_at' => now(),
        ]);
    }
}
