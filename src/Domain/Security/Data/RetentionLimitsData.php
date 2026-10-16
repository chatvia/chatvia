<?php

namespace Domain\Security\Data;

use Domain\Conversation\Services\ConversationRetention;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Retention bounds for the Security settings form: conversations cap at the
 * install's retention (shorten only); audit sits between the mandated floor
 * and default; leads and analytics go up to `max_days`; anonymize is capped
 * by the conversation retention on the page itself.
 */
#[TypeScript]
class RetentionLimitsData extends Data
{
    public function __construct(
        public int $conversations_max,
        public int $conversations_floor,
        public int $audit_default,
        public int $audit_floor,
        public int $leads_floor,
        public int $analytics_floor,
        public int $anonymize_floor,
        public int $max_days,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            conversations_max: ConversationRetention::days(),
            conversations_floor: (int) config('chatvia.retention.conversations_floor_days', 1),
            audit_default: (int) config('chatvia.security_audit.retention_days', 365),
            audit_floor: (int) config('chatvia.security_audit.retention_floor_days', 30),
            leads_floor: (int) config('chatvia.retention.leads_floor_days', 1),
            analytics_floor: (int) config('chatvia.retention.analytics_floor_days', 30),
            anonymize_floor: (int) config('chatvia.retention.anonymize_floor_days', 1),
            max_days: (int) config('chatvia.retention.max_days', 3650),
        );
    }
}
