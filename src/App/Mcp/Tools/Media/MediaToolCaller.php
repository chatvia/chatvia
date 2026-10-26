<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Media;

use App\Mcp\McpCallerContext;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Laravel\Mcp\Response;

/**
 * The check both media tools make first: a file belongs to one agent's
 * conversation, so a call without both, or with ones that do not match, is
 * refused with the reason.
 */
final class MediaToolCaller
{
    /** The error to return, or null when the caller is a known agent in its own conversation. */
    public static function refusal(McpCallerContext $caller): ?Response
    {
        return match (true) {
            $caller->agentExternalId === null => Response::error('Missing X-Chatvia-Agent-Id identity header.'),
            $caller->conversationExternalId === null => Response::error('Missing X-Chatvia-Conversation-Id header. A file can only be made inside an active chat conversation.'),
            ! $caller->agent instanceof Agent => Response::error("Unknown agent \"{$caller->agentExternalId}\"."),
            ! $caller->conversation instanceof Conversation => Response::error('Conversation not found or does not belong to this agent.'),
            default => null,
        };
    }
}
