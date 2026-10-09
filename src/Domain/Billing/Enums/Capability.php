<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;

/**
 * A plan feature a workspace may or may not have. The value is the matching
 * property of {@see BillingPackageCapabilitiesData} and the argument of the
 * `capability:<value>` route middleware, so values must never change.
 */
enum Capability: string
{
    case Sentiment = 'sentiment';
    case Tone = 'tone';
    case Topics = 'topics';
    case Handoff = 'handoff';
    case Api = 'api';
    case Pulse = 'pulse';
    case Evals = 'evals';
    case Guardrails = 'guardrails';
    case Audit = 'audit';

    /** Human-readable label for this capability. */
    public function label(): string
    {
        return match ($this) {
            self::Sentiment => 'Sentiment Analysis',
            self::Tone => 'Tone Analysis',
            self::Topics => 'Topic Analysis',
            self::Handoff => 'Handoff Support',
            self::Api => 'API Access',
            self::Pulse => 'Conversation Pulse',
            self::Evals => 'Evals',
            self::Guardrails => 'Guardrails',
            self::Audit => 'Audit Log',
        };
    }
}
