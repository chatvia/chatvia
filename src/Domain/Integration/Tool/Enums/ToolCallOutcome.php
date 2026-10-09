<?php

namespace Domain\Integration\Tool\Enums;

/**
 * How one tool call was resolved, as recorded in the governance audit:
 *
 *   - Auto        → ran without a human in the loop.
 *   - Approved    → was gated, and a person approved it before it ran.
 *   - Denied      → was gated, and a person declined it, so it never ran.
 *   - RateLimited → was blocked by the per-tool rate limit, so it never ran.
 *
 * The values are stored in `agent_activity` rows and filtered on in the audit
 * screens, so they must not change.
 */
enum ToolCallOutcome: string
{
    case Auto = 'auto';
    case Approved = 'approved';
    case Denied = 'denied';
    case RateLimited = 'rate_limited';

    /**
     * True when a person decided the call (approved or denied it).
     */
    public function wasGated(): bool
    {
        return $this === self::Approved || $this === self::Denied;
    }
}
