<?php

namespace Domain\Chat\Services\Tooling;

use Domain\Agents\Models\Agent;

/**
 * The identity headers stamped on every outbound tool call (function
 * webhooks + MCP requests) so the receiver knows which agent — and which
 * owning account — triggered it. Both values are opaque public UUIDs.
 */
final class ToolCallerIdentity
{
    public const AGENT_HEADER = 'X-Chatvia-Agent-Id';

    public const USER_HEADER = 'X-Chatvia-User-Id';

    /**
     * @return array<string, string>
     */
    public static function headers(Agent $agent): array
    {
        return [
            self::AGENT_HEADER => $agent->external_id,
            self::USER_HEADER => $agent->user->external_id,
        ];
    }
}
