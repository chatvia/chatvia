<?php

declare(strict_types=1);

namespace Domain\Guardrail\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The safety guards. PII is regex-based; injection, blocked topics and
 * toxicity are resolved by a single open-weight classifier call (injection in
 * knowledge and tool results by rules); a prompt leak is an answer that
 * repeats the system prompt's marker.
 */
#[TypeScript]
enum GuardType: string
{
    case PromptInjection = 'prompt_injection';
    case Pii = 'pii';
    case BlockedTopic = 'blocked_topic';
    case Toxicity = 'toxicity';
    case PromptLeak = 'prompt_leak';

    /**
     * The guards that catch a person misusing the agent (as opposed to data
     * or topics), which the Guardrails page lets an admin block them for.
     *
     * @return list<self>
     */
    public static function abusive(): array
    {
        return [self::PromptInjection, self::PromptLeak, self::Toxicity];
    }

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
