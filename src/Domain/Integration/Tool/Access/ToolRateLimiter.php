<?php

declare(strict_types=1);

namespace Domain\Integration\Tool\Access;

use Domain\Agent\Models\Agent;
use Domain\Integration\Tool\ValueObjects\ToolName;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Per-agent-per-tool call-rate guard over a fixed window (the window starts
 * with the first call and resets after `tool_rate_window_seconds`),
 * cache-backed via the framework {@see RateLimiter}. The limit and window are
 * Settings → Security (`chatvia.governance.tool_rate_*`). Generous by default
 * so it stays invisible until it is tightened; an exhausted
 * window makes {@see ToolAccessPolicy} return Deny, and the runner then
 * synthesizes a recoverable "rate-limited" tool result and records it in the
 * audit log.
 */
class ToolRateLimiter
{
    /**
     * Count the call and return true when it is within budget for the current
     * window, false once the limit is used up.
     */
    public function allow(Agent $agent, ToolName $tool): bool
    {
        $max = (int) config('chatvia.governance.tool_rate_limit', 60);

        // 0 / negative disables the limit entirely.
        if ($max <= 0) {
            return true;
        }

        $window = (int) config('chatvia.governance.tool_rate_window_seconds', 60);
        $key = 'tool-rate:'.$agent->id.':'.sha1($tool->value);

        // A single atomic increment, so parallel tool calls can't both pass a
        // separate "check" before either "hit" lands.
        return RateLimiter::increment($key, $window) <= $max;
    }
}
