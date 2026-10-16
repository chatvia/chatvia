<?php

declare(strict_types=1);

namespace Domain\Llm\Enums;

use Domain\Llm\Services\TaskModels;

/**
 * The app's own LLM calls, one case per task. An admin may pick a catalog
 * model for each in Settings → AI models; a task without one uses the model
 * for app tasks, then the chat model ({@see TaskModels}).
 *
 * - Chat: what new agents answer with, and the analytics assistant.
 * - During a chat, before the reply: FollowUp (rewrites a follow-up for the
 *   search), ToolRouting (picks the connector a turn is about), Guardrails.
 * - After a reply: Titles, ConversationAnalysis (topics, sentiment, tone).
 * - Agent setup: Starters, Translation (agent texts), FormText, ChatAddress
 *   (hosted chat slug ideas).
 * - Training sources: Enhancement (chunk summaries and questions), Tables,
 *   Images.
 * - Testing: EvalJudge.
 *
 * Each value names its config key and its admin setting, so a new case
 * needs both (see `settingsProperty()`).
 */
enum LlmTask: string
{
    case Chat = 'chat';
    case FollowUp = 'follow_up';
    case ToolRouting = 'tool_routing';
    case Guardrails = 'guardrails';
    case Titles = 'titles';
    case ConversationAnalysis = 'conversation_analysis';
    case Starters = 'starters';
    case Translation = 'translation';
    case FormText = 'form_text';
    case ChatAddress = 'chat_address';
    case Enhancement = 'enhancement';
    case Tables = 'tables';
    case Images = 'images';
    case EvalJudge = 'eval_judge';

    /** The config key that holds the task's model alias (`llms.external_name`). */
    public function configKey(): string
    {
        return match ($this) {
            self::Chat => 'chatvia.agent.default_model',
            self::FollowUp, self::ToolRouting, self::Guardrails, self::Titles,
            self::ConversationAnalysis, self::Starters, self::Translation,
            self::FormText, self::ChatAddress, self::Enhancement, self::Tables,
            self::Images, self::EvalJudge => "chatvia.ai.tasks.{$this->value}",
        };
    }

    /** The AiSettings property that fills {@see configKey()}. */
    public function settingsProperty(): string
    {
        return match ($this) {
            self::Chat => 'default_agent_model',
            self::FollowUp, self::ToolRouting, self::Guardrails, self::Titles,
            self::ConversationAnalysis, self::Starters, self::Translation,
            self::FormText, self::ChatAddress, self::Enhancement, self::Tables,
            self::Images, self::EvalJudge => "{$this->value}_model",
        };
    }

    /**
     * The tasks an admin may give their own model: every case but Chat.
     *
     * @return list<self>
     */
    public static function appTasks(): array
    {
        return array_values(array_filter(self::cases(), fn (self $task): bool => $task !== self::Chat));
    }
}
