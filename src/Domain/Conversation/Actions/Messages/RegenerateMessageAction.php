<?php

namespace Domain\Conversation\Actions\Messages;

use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Data\Messages\RegeneratedTurnData;
use Domain\Conversation\Services\Memory\ChatMemory;

/**
 * Prepares a "regenerate answer": removes the last exchange (the visitor's
 * message and the assistant's answer) from the live history and hands back
 * the visitor's text, which the client sends again as a new turn. Returns
 * null when the visitor message is not in the history.
 */
class RegenerateMessageAction
{
    public function __construct(
        private readonly ChatMemory $memory,
        private readonly DeleteMessagePairAction $deletePair,
    ) {}

    public function execute(ChatScopeData $scope, string $assistantMessageId, string $userMessageId): ?RegeneratedTurnData
    {
        $memory = $this->memory->withScope($scope);
        $userMessage = $memory->getMessageById($userMessageId);

        if (! $userMessage instanceof MessageData) {
            return null;
        }

        $this->deletePair->execute($scope, $assistantMessageId);

        // When the answer never landed, the visitor message is still in the
        // history: it goes on its own, never with the answer before it.
        $messages = $memory->cachedMessages();
        $remaining = array_values(array_filter($messages, static fn (array $message): bool => ($message['id'] ?? null) !== $userMessageId));

        if (count($remaining) !== count($messages)) {
            $memory->saveMessages($remaining);
        }

        return new RegeneratedTurnData(
            userMessageContent: $userMessage->content,
            createdAt: now()->toISOString(),
        );
    }
}
