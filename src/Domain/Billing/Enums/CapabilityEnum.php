<?php

namespace Domain\Billing\Enums;

enum CapabilityEnum: string
{
    case SENTIMENT = 'sentiment';
    case TONE = 'tone';
    case TOPICS = 'topics';
    case HANDOFF = 'handoff';
    case API = 'api';
    case PULSE = 'pulse';
    case EVALS = 'evals';
    case GUARDRAILS = 'guardrails';
    case AUDIT = 'audit';

    /**
     * Get a human-readable label for this capability
     */
    public function label(): string
    {
        return match ($this) {
            self::SENTIMENT => 'Sentiment Analysis',
            self::TONE => 'Tone Analysis',
            self::TOPICS => 'Topic Analysis',
            self::HANDOFF => 'Handoff Support',
            self::API => 'API Access',
            self::PULSE => 'Conversation Pulse',
            self::EVALS => 'Evals',
            self::GUARDRAILS => 'Guardrails',
            self::AUDIT => 'Audit Log',
        };
    }
}
