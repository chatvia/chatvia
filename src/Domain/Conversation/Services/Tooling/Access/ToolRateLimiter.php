<?php

namespace Domain\Conversation\Services\Tooling\Access;

use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Per-agent-per-tool call-rate guard, cache-backed via the framework
 * {@see RateLimiter} (the codebase's chosen mechanism — see
 * AppServiceProvider::configureRateLimiters). Generous by default so it stays
 * invisible until a workspace tightens it; an exhausted window makes
 * {@see ToolAccessPolicy} return Deny, and the runner then synthesizes a
 * recoverable "rate-limited" tool result and records it in the audit log.
 */
class ToolRateLimiter
{
    /**
     * Consume a slot and return true when the call is within budget; return
     * false (without consuming) when the per-window limit is exhausted.
     */
    public function allow(Agent $agent, string $toolName): bool
    {
        $max = (int) config('chatvia.governance.tool_rate_limit', 60);

        // 0 / negative disables the limit entirely.
        if ($max <= 0) {
            return true;
        }

        $window = (int) config('chatvia.governance.tool_rate_window_seconds', 60);
        $key = 'tool-rate:'.$agent->id.':'.sha1($toolName);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            return false;
        }

        RateLimiter::hit($key, $window);

        return true;
    }
}
