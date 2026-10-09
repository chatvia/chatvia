<?php

namespace Domain\Conversation\Actions\Messages;

use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Data\Messages\AssistantTurnData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Jobs\StoreMessageJob;
use Domain\Conversation\Services\Memory\ChatMemory;

/**
 * Adds a visitor message or an assistant reply to a conversation: into the
 * live chat memory at once (so the next turn and the history see it), and
 * into the database through the queued {@see StoreMessageJob} — unless the
 * owner switched the agent's chat log off.
 */
class AddMessageToConversationAction
{
    public function __construct(
        private readonly ChatMemory $memory,
    ) {}

    /**
     * @return string the message's external id
     */
    public function execute(ChatScopeData $scope, UserTurnData|AssistantTurnData $turn): string
    {
        $this->memory->withScope($scope)->appendMessage($this->toMessageData($turn));

        // With the chat log off the live conversation still works; it is just
        // never written to the database, which also skips every analytic.
        if ($scope->agent->chatlogEnabled()) {
            StoreMessageJob::dispatch($scope->agent, $turn);
        }

        return $turn->external_message_id;
    }

    private function toMessageData(UserTurnData|AssistantTurnData $turn): MessageData
    {
        $isUserTurn = $turn instanceof UserTurnData;

        return new MessageData(
            id: $turn->external_message_id,
            role: $isUserTurn ? MessageRole::User : MessageRole::Assistant,
            content: $turn->message,
            type: MessageData::TYPE_TEXT,
            timestamp: now()->timestamp,
            model: $turn->model,
            language: $turn->language,
            message_for_llm: $isUserTurn ? $turn->message_for_llm : null,
            has_llm_found_answer: $isUserTurn ? null : $turn->has_llm_found_answer,
            metadata: $isUserTurn ? null : $turn->storedMetadata(),
        );
    }
}
