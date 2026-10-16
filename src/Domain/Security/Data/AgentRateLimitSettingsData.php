<?php

namespace Domain\Security\Data;

use Domain\Agent\Models\Agent;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The agent's chat rate-limit settings as the Rate limiting page edits them:
 * the per-minute caps (across all visitors, and per visitor) and whether the
 * owner is emailed when the agent-wide cap trips.
 */
#[TypeScript]
class AgentRateLimitSettingsData extends Data
{
    public function __construct(
        public int $chat_rate_limit_per_minute,
        public bool $notify_owner_on_rate_limit,
        public int $visitor_rate_limit_per_minute,
    ) {}

    public static function fromAgent(Agent $agent): self
    {
        $security = $agent->settings('security');

        return new self(
            chat_rate_limit_per_minute: (int) $security->get('chat_rate_limit_per_minute', 20),
            notify_owner_on_rate_limit: (bool) $security->get('notify_owner_on_rate_limit', false),
            visitor_rate_limit_per_minute: (int) $security->get('visitor_rate_limit_per_minute', 8),
        );
    }
}
