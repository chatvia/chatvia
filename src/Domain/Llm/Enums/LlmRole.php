<?php

declare(strict_types=1);

namespace Domain\Llm\Enums;

/**
 * The jobs the app's own LLM calls are grouped into. An admin picks a catalog
 * model for each in Settings → AI models; a role without one uses the chat model.
 *
 * - Chat: what new agents answer with, and the analytics assistant.
 * - Fast: short, frequent calls (titles, topics, tone, guardrails, slugs,
 *   form text, starters, translation, query rewriting, tool routing, the eval
 *   judge).
 * - Smart: reading content (page summaries for chunking, tables).
 * - Vision: describing images.
 */
enum LlmRole: string
{
    case Chat = 'chat';
    case Fast = 'fast';
    case Smart = 'smart';
    case Vision = 'vision';

    /** The config key that holds the role's model alias (`llms.external_name`). */
    public function configKey(): string
    {
        return match ($this) {
            self::Chat => 'chatvia.agent.default_model',
            default => "chatvia.ai.roles.{$this->value}",
        };
    }
}
