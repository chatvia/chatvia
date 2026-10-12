<?php

declare(strict_types=1);

namespace Domain\Integration\Tool\Dispatch;

use Domain\Agent\Models\Agent;

/**
 * The identity headers stamped on every outbound tool call (function
 * webhooks + MCP requests) so the receiver knows which agent — and which
 * workspace — triggered it, and, when a signed-in chatter is talking,
 * which end-user. All values are opaque public UUIDs; the chatter id is the
 * one the customer received from POST /api/v1/chatters.
 *
 * The `X-Chatvia-*` header names are a public integration contract, so they
 * stay the same on a rebranded install.
 */
final class ToolCallerIdentity
{
    /**
     * @return array<string, string>
     */
    public static function headers(Agent $agent, ?string $conversationExternalId = null, ?string $chatterExternalId = null): array
    {
        $headers = [
            'X-Chatvia-Agent-Id' => $agent->external_id,
            'X-Chatvia-Workspace-Id' => $agent->workspaceExternalId(),
            'X-Chatvia-Metadata' => json_encode($agent->metadata),
        ];

        if ($conversationExternalId !== null) {
            $headers['X-Chatvia-Conversation-Id'] = $conversationExternalId;
        }

        if ($chatterExternalId !== null) {
            $headers['X-Chatvia-Chatter-Id'] = $chatterExternalId;
        }

        return $headers;
    }
}
