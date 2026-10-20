<?php

declare(strict_types=1);

namespace Domain\Llm\Enums;

/**
 * The size of model a job needs. Large writes what people read (agent
 * answers, translations) or judges them; Small does the many short jobs
 * around a reply (guardrails, follow-up questions, titles…), where speed
 * and price matter more. {@see LlmTask::tier()} says which task needs
 * which; {@see AiProvider::presetChatModels()} and
 * {@see AiProvider::presetQuickTaskModels()} which models of a provider fit
 * each. The admin never sees the word: the setup calls them the chat models
 * and the model for quick tasks.
 */
enum ModelTier: string
{
    case Large = 'large';
    case Small = 'small';
}
