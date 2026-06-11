<?php

namespace Domain\Channel\Telegram\Services;

use Domain\Channel\Services\ThreadMappingGuard;
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
        private readonly ThreadMappingGuard $guard,
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
            if ($this->guard->conversationExists($existing->conversation_external_id)) {
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

        $conversationId = $this->memory
            ->withContext($agent, $session->external_id)
            ->createConversation(
                title: $firstName !== null ? "Telegram · {$firstName}" : 'Telegram',
                source: ConversationSource::Telegram,
            );

        return TelegramChat::create([
            'agent_telegram_bot_id' => $bot->id,
            'chat_id' => (string) $chatId,
            'session_external_id' => $session->external_id,
            'conversation_external_id' => $conversationId,
            'first_name' => $firstName,
            'username' => $from['username'] ?? null,
            'last_message_at' => now(),
        ]);
    }
}
