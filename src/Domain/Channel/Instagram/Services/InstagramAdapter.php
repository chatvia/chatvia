<?php

namespace Domain\Channel\Instagram\Services;

use Domain\Agent\Models\Agent;
use Domain\Channel\Contracts\SendsTypingIndicator;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Instagram\Models\InstagramThread;
use Domain\Conversation\Enums\ConversationSource;

/**
 * The Instagram side of a channel turn: one professional account (through its
 * linked Facebook Page's token) talking to one Instagram user (IGSID).
 * Instagram messages can't be edited, so the reply is sent whole while the
 * "typing…" indicator (cleared by Instagram after ~20s) is kept alive.
 */
final readonly class InstagramAdapter implements SendsTypingIndicator
{
    public function __construct(
        private InstagramClient $client,
        private InstagramThreadResolver $resolver,
        private AgentInstagramAccount $account,
        private string $igsid,
    ) {}

    public function agent(): Agent
    {
        return $this->account->agent;
    }

    public function source(): ConversationSource
    {
        return ConversationSource::Instagram;
    }

    public function displayName(): string
    {
        return 'Instagram';
    }

    public function resolveConversation(): InstagramThread
    {
        return $this->resolver->resolve($this->account, $this->igsid);
    }

    public function sendMessage(string $markdown): void
    {
        $this->client->sendMessage($this->account->page_access_token, $this->igsid, $markdown);
    }

    public function sendTyping(): void
    {
        $this->client->sendTypingAction($this->account->page_access_token, $this->igsid);
    }

    public function typingRefreshSeconds(): float
    {
        return 15.0;
    }

    public function requestIdPrefix(): string
    {
        return 'ig_';
    }

    public function logContext(): array
    {
        return ['igsid' => $this->igsid];
    }
}
