<?php

namespace Domain\Integration\Tool\Access;

use Domain\Agent\Models\Agent;
use Domain\Integration\Tool\Data\ToolName;
use Domain\Integration\Tool\Enums\ToolAccessDecision;

/**
 * The single dispatch-time governance gate for a tool call. It composes the
 * existing per-tool approval policy with a per-agent-per-tool rate limit and
 * returns one decision the runner acts on:
 *
 *   - RequiresApproval → existing human-in-the-loop checkpoint.
 *   - Deny             → blocked (rate limit); runner synthesizes a recoverable
 *                        result and audits it.
 *   - Allow            → runs inline.
 *
 * Static `Blocked` tools never reach here — ToolRegistry excludes them from the
 * catalog — so today Deny is purely the rate-limit outcome. Per-agent overrides
 * can extend Deny later behind this same interface.
 *
 * Approval is resolved through {@see ToolApprovalPolicy} (which memoizes per
 * request); the rate limit is intentionally NOT memoized — each call must
 * consume a real slot.
 */
class ToolAccessPolicy
{
    public function __construct(
        private readonly ToolApprovalPolicy $approvalPolicy,
        private readonly ToolRateLimiter $rateLimiter,
    ) {}

    public function decide(Agent $agent, ToolName $tool): ToolAccessDecision
    {
        if ($this->approvalPolicy->requiresApproval($agent, $tool)) {
            return ToolAccessDecision::RequiresApproval;
        }

        // Gated tools are already throttled by the human-in-the-loop, so only
        // auto-run (Allow-tier) tools are subject to the rate limit.
        if (! $this->rateLimiter->allow($agent, $tool)) {
            return ToolAccessDecision::Deny;
        }

        return ToolAccessDecision::Allow;
    }
}
