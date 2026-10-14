<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Conversations;

use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Llm\Operations\Query\TitleGenerator;

/**
 * Names a conversation from its first exchange with one short LLM call.
 * Does nothing while the conversation has no messages yet, or once it has a
 * title: the browser and the queued fallback may both ask, and a name given
 * by hand is kept.
 */
class GenerateConversationTitleAction
{
    /** How many recent messages the title is generated from: the first question and its answer. */
    private const int TITLE_SOURCE_MESSAGES = 2;

    public function __construct(
        private readonly ChatMemory $memory,
        private readonly TitleGenerator $titleGenerator,
        private readonly RenameConversationAction $renameConversation,
    ) {}

    public function execute(ChatScopeData $scope): void
    {
        $conversation = Conversation::query()
            ->inSession($scope->sessionId, $scope->agent->id)
            ->where('external_id', $scope->conversationId)
            ->first();

        if ($conversation instanceof Conversation && ! $conversation->needsTitle()) {
            return;
        }

        $transcript = $this->memory->withScope($scope)->llmTranscript(self::TITLE_SOURCE_MESSAGES);

        if ($transcript === '') {
            return;
        }

        $this->renameConversation->execute($scope, $this->titleGenerator->generate($transcript));
    }
}
