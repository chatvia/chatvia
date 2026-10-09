<?php

namespace Domain\Channel\Teams\Services;

use Domain\Agent\Models\Agent;
use Domain\Channel\Contracts\StreamsReplies;
use Domain\Channel\Teams\Data\TeamsActivityData;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Models\TeamsChat;
use Domain\Conversation\Enums\ConversationSource;

/**
 * The Microsoft Teams side of a channel turn: one Azure bot talking in one
 * Teams conversation (personal chat, group chat or channel thread). Teams
 * activities can be edited, so the reply streams into a placeholder; the
 * connector throttles per-conversation bot traffic harder than Slack, hence
 * the 2s edit throttle.
 */
final readonly class TeamsAdapter implements StreamsReplies
{
    public function __construct(
        private TeamsClient $client,
        private TeamsChatResolver $resolver,
        private AgentTeamsBot $bot,
        private TeamsActivityData $activity,
    ) {}

    public function activity(): TeamsActivityData
    {
        return $this->activity;
    }

    public function agent(): Agent
    {
        return $this->bot->agent;
    }

    public function source(): ConversationSource
    {
        return ConversationSource::MsTeams;
    }

    public function displayName(): string
    {
        return 'Microsoft Teams';
    }

    public function resolveConversation(): TeamsChat
    {
        return $this->resolver->resolve(
            $this->bot,
            $this->activity->conversationId,
            $this->activity->serviceUrl,
            $this->activity->conversationType,
            $this->activity->senderName,
        );
    }

    public function sendMessage(string $markdown): void
    {
        $this->postMessage($markdown);
    }

    public function postMessage(string $markdown): ?string
    {
        return $this->client->postMessage($this->bot, $this->activity->serviceUrl, $this->activity->conversationId, $markdown);
    }

    public function updateMessage(string $messageId, string $markdown): bool
    {
        return $this->client->updateMessage($this->bot, $this->activity->serviceUrl, $this->activity->conversationId, $messageId, $markdown);
    }

    public function streamThrottleSeconds(): float
    {
        return 2.0;
    }

    public function requestIdPrefix(): string
    {
        return 'mst_';
    }

    public function logContext(): array
    {
        return ['conversation_id' => $this->activity->conversationId];
    }
}
