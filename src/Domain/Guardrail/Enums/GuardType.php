<?php

declare(strict_types=1);

namespace Domain\Guardrail\Enums;

/**
 * The four safety guards. PII is regex-based; the other three are resolved by a
 * single open-weight classifier call.
 */
enum GuardType: string
{
    case PromptInjection = 'prompt_injection';
    case Pii = 'pii';
    case BlockedTopic = 'blocked_topic';
    case Toxicity = 'toxicity';

    public function label(): string
    {
        return match ($this) {
            self::PromptInjection => 'Prompt injection',
            self::Pii => 'PII',
            self::BlockedTopic => 'Blocked topic',
            self::Toxicity => 'Toxicity',
        };
    }
}
