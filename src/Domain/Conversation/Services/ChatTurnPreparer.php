<?php

declare(strict_types=1);

namespace Domain\Conversation\Services;

use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Services\Turn\KnowledgeEnricher;
use Domain\Conversation\Services\Turn\SystemPromptBuilder;
use Domain\Conversation\Services\Turn\TurnContextFactory;

/**
 * Prepares a chat turn in the three steps every surface runs in the same
 * order — the web stream, the messaging channels and evals: build the turn's
 * context, add the agent's knowledge, then assemble the LLM messages. The
 * work is done by {@see TurnContextFactory}, {@see KnowledgeEnricher} and
 * {@see SystemPromptBuilder}; this is the one entry point they share, so the
 * surfaces cannot drift apart.
 */
readonly class ChatTurnPreparer
{
    public function __construct(
        private TurnContextFactory $contexts,
        private KnowledgeEnricher $knowledge,
        private SystemPromptBuilder $prompts,
    ) {}

    public function initializeChatContext(StreamRequestData $data): ChatContext
    {
        return $this->contexts->forNewTurn($data);
    }

    public function enrichContextWithKnowledge(ChatContext $context): ChatContext
    {
        return $this->knowledge->enrich($context);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function prepareLlmMessages(ChatContext $context): array
    {
        return $this->prompts->messagesFor($context);
    }
}
