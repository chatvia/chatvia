<?php

namespace App\Mcp;

use App\Http\Middleware\Auth\VerifyInternalMcpRequest;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;

/**
 * Who is calling one of Chatvia's built-in MCP tools. It is resolved once per
 * `tools/call` by {@see VerifyInternalMcpRequest} from the signed `X-Chatvia-*`
 * identity headers and the connector key McpClient forwards, then bound in the
 * container so tools receive it by injection instead of reading headers.
 *
 * The raw external ids sit next to the resolved models so a tool can tell "no
 * header" apart from "unknown id" when it explains a refusal. Every field
 * defaults to null, which is also what a tool sees when no caller was resolved.
 */
final readonly class McpCallerContext
{
    public function __construct(
        /** The `X-Chatvia-Agent-Id` header, as sent. */
        public ?string $agentExternalId = null,
        /** The agent that header names, when it exists. */
        public ?Agent $agent = null,
        /** The `X-Chatvia-Conversation-Id` header, as sent. */
        public ?string $conversationExternalId = null,
        /** That conversation, only when it belongs to {@see $agent}. */
        public ?Conversation $conversation = null,
        /** The signed-in chatter's public id, when one is talking. */
        public ?string $chatterExternalId = null,
        /** The workspace the owner-facing analytics chat acts for (workspace mode). */
        public ?User $workspace = null,
        /** The workspace's own third-party API key for this connector (bearer token). */
        public ?string $connectorApiKey = null,
    ) {}
}
