<?php

namespace Domain\Channel\Telegram\Services;

use Domain\Agent\Models\Agent;
use Domain\Channel\Contracts\SendsTypingIndicator;
use Domain\Channel\Telegram\Data\TelegramSenderData;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Models\TelegramChat;
use Domain\Conversation\Enums\ConversationSource;

/**
 * The Telegram side of a channel turn: one bot talking to one Telegram chat.
 * Telegram messages can't be edited, so the reply is sent whole while the
 * "typing…" chat action (cleared by Telegram after ~5s) is kept alive.
 */
final readonly class TelegramAdapter implements SendsTypingIndicator
{
    public function __construct(
        private TelegramClient $client,
        private TelegramChatResolver $resolver,
        private AgentTelegramBot $bot,
        private string $chatId,
        private TelegramSenderData $sender = new TelegramSenderData,
    ) {}

    public function bot(): AgentTelegramBot
    {
        return $this->bot;
    }

    public function sender(): TelegramSenderData
    {
        return $this->sender;
    }

    public function agent(): Agent
    {
        return $this->bot->agent;
    }

    public function source(): ConversationSource
    {
        return ConversationSource::Telegram;
    }

    public function displayName(): string
    {
        return 'Telegram';
    }

    public function resolveConversation(): TelegramChat
    {
        return $this->resolver->resolve($this->bot, $this->chatId, $this->sender);
    }

    public function sendMessage(string $markdown): void
    {
        $this->client->sendMessage($this->bot->bot_token, $this->chatId, $markdown);
    }

    public function sendTyping(): void
    {
        $this->client->sendChatAction($this->bot->bot_token, $this->chatId);
    }

    public function typingRefreshSeconds(): float
    {
        return 4.0;
    }

    public function requestIdPrefix(): string
    {
        return 'tg_';
    }

    public function logContext(): array
    {
        return ['chat_id' => $this->chatId];
    }
}
