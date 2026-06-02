<?php

namespace Domain\Chat\Actions\Messages;

use Domain\Agent\Models\Agent;
use Domain\Chat\Data\Messages\RegenerateMessageResult;
use Domain\Chat\Services\Memory\ChatMemory;
use Domain\Chat\Services\Memory\ChatSession;

class RegenerateChatMessageAction
{
    public function __construct(
        private readonly ChatMemory $memory,
        private readonly ChatSession $session,
    ) {}

    public function execute(
        Agent $agent,
        string $conversationId,
        string $assistantMessageId,
        string $userMessageId,
    ): ?RegenerateMessageResult {
        $this->memory
            ->setAgent($agent)
            ->setExternalSessionId($this->session->firstOrCreateChatSession($agent))
            ->setConversationId($conversationId);

        $userMessage = $this->memory->getMessageById($userMessageId);

        if (! $userMessage) {
            return null;
        }

        $userMessageContent = $userMessage->content ?? '';

        $this->memory->deleteMessage($assistantMessageId);
        $this->memory->deleteMessage($userMessageId);

        return new RegenerateMessageResult(
            userMessageContent: $userMessageContent,
            createdAt: now()->toISOString(),
        );
    }
}
