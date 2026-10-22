<?php

namespace Domain\Conversation\Actions\Messages;

use App\Identity\Workspace\Actions\MessageLimits\RecordMessageUsageAction;
use App\Identity\Workspace\Services\MessageAllowance;
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
 * owner switched the agent's chat log off. A chatter's message is counted
 * against their monthly limit here too, so the count never depends on the
 * chat log.
 */
class AddMessageToConversationAction
{
    public function __construct(
        private readonly ChatMemory $memory,
        private readonly RecordMessageUsageAction $recordUsage,
        private readonly MessageAllowance $allowance,
    ) {}

    /**
     * @return string the message's external id
     */
    public function execute(ChatScopeData $scope, UserTurnData|AssistantTurnData $turn): string
    {
        $this->memory->withScope($scope)->appendMessage($this->toMessageData($turn));

        // A chatter's message counts against the chatter's own monthly limit
        // (the workspace's limit counts replies). Counted on the turn itself —
        // like the reply, via AssistantReplyCompleted — never in the queued
        // chat-log write, which an agent's chat log switched off skips. A
        // no-op for a chatter without a limit.
        if ($turn instanceof UserTurnData && $turn->chatter_id !== null) {
            $this->recordUsage->execute($this->allowance->limitForChatter($turn->chatter_id));
        }

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
            llm_id: $turn->llm_id,
            language: $turn->language,
            message_for_llm: $isUserTurn ? $turn->message_for_llm : null,
            has_llm_found_answer: $isUserTurn ? null : $turn->has_llm_found_answer,
            metadata: $isUserTurn ? null : $turn->storedMetadata(),
        );
    }
}
