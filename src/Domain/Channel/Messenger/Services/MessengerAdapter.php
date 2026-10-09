<?php

namespace Domain\Channel\Messenger\Services;

use Domain\Agent\Models\Agent;
use Domain\Channel\Contracts\SendsTypingIndicator;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Models\MessengerThread;
use Domain\Conversation\Enums\ConversationSource;

/**
 * The Messenger side of a channel turn: one Facebook Page talking to one
 * page-scoped user (PSID). Messenger messages can't be edited, so the reply is
 * sent whole while the "typing…" indicator (cleared by Messenger after ~20s)
 * is kept alive.
 */
final readonly class MessengerAdapter implements SendsTypingIndicator
{
    public function __construct(
        private MessengerClient $client,
        private MessengerThreadResolver $resolver,
        private AgentMessengerPage $page,
        private string $psid,
    ) {}

    public function agent(): Agent
    {
        return $this->page->agent;
    }

    public function source(): ConversationSource
    {
        return ConversationSource::Messenger;
    }

    public function displayName(): string
    {
        return 'Messenger';
    }

    public function resolveConversation(): MessengerThread
    {
        return $this->resolver->resolve($this->page, $this->psid);
    }

    public function sendMessage(string $markdown): void
    {
        $this->client->sendMessage($this->page->page_access_token, $this->psid, $markdown);
    }

    public function sendTyping(): void
    {
        $this->client->sendTypingAction($this->page->page_access_token, $this->psid);
    }

    public function typingRefreshSeconds(): float
    {
        return 15.0;
    }

    public function requestIdPrefix(): string
    {
        return 'msgr_';
    }

    public function logContext(): array
    {
        return ['psid' => $this->psid];
    }
}
