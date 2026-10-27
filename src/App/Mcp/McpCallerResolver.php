<?php

namespace App\Mcp;

use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Turns the identity of a signed internal MCP request into a
 * {@see McpCallerContext}. Only call it after the request's HMAC has been
 * verified: the signature is what makes these headers trustworthy.
 *
 * The workspace header is honoured only on the servers the owner-facing
 * "Ask your data" chat calls (config('mcp.builtin')). Everywhere else a tool
 * runs for an agent, never for a bare workspace.
 */
final class McpCallerResolver
{
    private const string AGENT_HEADER = 'X-Chatvia-Agent-Id';

    private const string CONVERSATION_HEADER = 'X-Chatvia-Conversation-Id';

    private const string CHATTER_HEADER = 'X-Chatvia-Chatter-Id';

    private const string WORKSPACE_HEADER = 'X-Chatvia-Workspace-Id';

    public function resolve(Request $request): McpCallerContext
    {
        $agentExternalId = $this->header($request, self::AGENT_HEADER);
        $conversationExternalId = $this->header($request, self::CONVERSATION_HEADER);

        $agent = $agentExternalId === null
            ? null
            : Agent::query()->where('external_id', $agentExternalId)->first();

        return new McpCallerContext(
            agentExternalId: $agentExternalId,
            agent: $agent,
            conversationExternalId: $conversationExternalId,
            conversation: $this->conversation($agent, $conversationExternalId),
            chatterExternalId: $this->header($request, self::CHATTER_HEADER),
            workspace: $this->workspace($request),
            connectorApiKey: $this->bearerToken($request),
        );
    }

    /** The conversation, but only when it belongs to the calling agent. */
    private function conversation(?Agent $agent, ?string $externalId): ?Conversation
    {
        if (! $agent instanceof Agent || $externalId === null) {
            return null;
        }

        return Conversation::query()
            ->where('external_id', $externalId)
            ->forAgent($agent->id)
            ->first();
    }

    /**
     * The workspace named by the workspace header, only on the analytics
     * servers and only when no agent is calling (an agent's tool calls carry
     * the workspace id too, but never act in workspace mode).
     */
    private function workspace(Request $request): ?Workspace
    {
        $externalId = $this->header($request, self::WORKSPACE_HEADER);

        if ($externalId === null
            || $this->header($request, self::AGENT_HEADER) !== null
            || ! $this->acceptsWorkspaceCaller($request)
            || ! Str::isUuid($externalId)) {
            return null;
        }

        return Workspace::query()->where('external_id', $externalId)->first();
    }

    /**
     * True for the servers the analytics chat uses — the only routes where a
     * workspace id alone identifies the caller.
     */
    private function acceptsWorkspaceCaller(Request $request): bool
    {
        $paths = array_map(
            static fn (string $slug): string => "mcp/{$slug}",
            array_column((array) config('mcp.builtin', []), 'slug'),
        );

        return collect($paths)->containsStrict($request->path());
    }

    private function header(Request $request, string $name): ?string
    {
        $value = $request->headers->get($name);

        return filled($value) ? $value : null;
    }

    private function bearerToken(Request $request): ?string
    {
        $token = $request->bearerToken();

        return filled($token) ? $token : null;
    }
}
