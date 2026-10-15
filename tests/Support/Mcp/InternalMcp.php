<?php

declare(strict_types=1);

namespace Tests\Support\Mcp;

use App\Identity\Workspace\Models\Workspace;
use App\Mcp\McpCallerContext;
use App\Mcp\McpCallerResolver;
use Domain\Integration\Mcp\Services\InternalMcpSignature;
use Illuminate\Http\Request;

/**
 * Calls Chatvia's own built-in MCP servers (`/mcp/*`) as the tool loop does:
 * a JSON-RPC body with the X-Chatvia-* identity headers, signed like
 * McpClient signs them. For a tool called directly (`Server::tool()`), the
 * caller is resolved from the same headers as the middleware resolves it.
 */
final class InternalMcp
{
    /**
     * A JSON-RPC `tools/call` body.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public static function toolCall(string $tool, array $arguments = []): array
    {
        return ['jsonrpc' => '2.0', 'id' => '1', 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => (object) $arguments]];
    }

    /**
     * The identity headers plus the signature of a POST of $payload to $path
     * (postJson() sends json_encode($payload), so that is the signed body).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $identity
     * @return array<string, string>
     */
    public static function signedHeaders(string $path, array $payload, array $identity): array
    {
        return array_merge($identity, InternalMcpSignature::headers('POST', $path, (string) json_encode($payload), $identity));
    }

    /**
     * Call a tool as the tool loop of this agent (and conversation); null
     * leaves that header out.
     *
     * @param  array<string, string>  $extraHeaders
     */
    public static function actAsAgent(?string $agentExternalId, ?string $conversationExternalId = null, array $extraHeaders = []): void
    {
        self::actWith('/mcp/forms', [
            ...array_filter([
                'X-Chatvia-Agent-Id' => $agentExternalId,
                'X-Chatvia-Conversation-Id' => $conversationExternalId,
            ]),
            ...$extraHeaders,
        ]);
    }

    /** Call a tool as the owner-facing analytics chat of this workspace, or with no identity. */
    public static function actAsWorkspace(?Workspace $workspace): void
    {
        self::actWith('/mcp/account-analytics', $workspace instanceof Workspace ? ['X-Chatvia-Workspace-Id' => $workspace->external_id] : []);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private static function actWith(string $path, array $headers): void
    {
        $request = Request::create($path, 'POST');
        $request->headers->add($headers);

        app()->instance(McpCallerContext::class, app(McpCallerResolver::class)->resolve($request));
    }
}
