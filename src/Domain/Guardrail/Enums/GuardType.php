<?php

declare(strict_types=1);

namespace Domain\Guardrail\Enums;

/**
 * The safety guards. PII is regex-based; injection, blocked topics and
 * toxicity are resolved by a single open-weight classifier call (injection in
 * knowledge and tool results by rules); a prompt leak is an answer that
 * repeats the system prompt's marker.
 */
enum GuardType: string
{
    case PromptInjection = 'prompt_injection';
    case Pii = 'pii';
    case BlockedTopic = 'blocked_topic';
    case Toxicity = 'toxicity';
    case PromptLeak = 'prompt_leak';

    public function label(): string
    {
        return match ($this) {
            self::PromptInjection => 'Prompt injection',
            self::Pii => 'PII',
            self::BlockedTopic => 'Blocked topic',
            self::Toxicity => 'Toxicity',
            self::PromptLeak => 'Prompt leak',
        };
    }
}
