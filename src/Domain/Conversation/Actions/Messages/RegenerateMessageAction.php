<?php

namespace Domain\Conversation\Actions\Messages;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Messages\RegenerateMessageResult;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Memory\TurnState;

class RegenerateMessageAction
{
    public function __construct(
        private readonly ChatMemory $memory,
        private readonly TurnState $session,
    ) {}

    public function execute(
        Agent $agent,
        string $conversationId,
        string $assistantMessageId,
        string $userMessageId,
    ): ?RegenerateMessageResult {
        $this->memory
            ->setAgent($agent)
            ->setExternalSessionId($this->session->firstOrCreateSession($agent))
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
