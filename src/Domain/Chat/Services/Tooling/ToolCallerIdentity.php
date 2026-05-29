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
    /**
     * @return array<string, string>
     */
    public static function headers(Agent $agent): array
    {
        return [
            'X-Chatvia-Agent-Id' => $agent->external_id,
            'X-Chatvia-User-Id' => $agent->user->external_id,
            'X-Chatvia-Metadata' => json_encode($agent->metadata),
        ];
    }
}
