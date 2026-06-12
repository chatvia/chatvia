<?php

namespace App\Http\Middleware\Auth;

use App\Identity\Workspace\Models\User;
use Closure;
use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\InternalMcpSignature;
use Illuminate\Http\Request;
use Support\Enums\User\UserType;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates Chatvia's OWN built-in MCP servers (/mcp/weather, /mcp/currency,
 * /mcp/charts, /mcp/leads, /mcp/perplexity). These are public HTTP routes but
 * must only ever be driven by Chatvia's server-side tool loop.
 *
 * Two checks:
 *   1. Proof-of-origin — a fresh HMAC signature attached by McpClient. Rejects
 *      anonymous internet callers (the prior unauthenticated-MCP hole).
 *   2. Authorisation — for a `tools/call`, EITHER:
 *      a. Per-agent enablement: the agent named in the identity header has THIS
 *         built-in connector attached + enabled (so a tool only runs for agents
 *         whose owner explicitly turned it on); OR
 *      b. Workspace mode: a signed `X-Chatvia-Workspace-Id` names a real
 *         workspace. Used by the owner-facing "Ask your data" analytics chat,
 *         which has no agent and a fixed internal toolset.
 */
class VerifyInternalMcpRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! InternalMcpSignature::verify(
            $request->header(InternalMcpSignature::TIMESTAMP_HEADER),
            $request->header(InternalMcpSignature::SIGNATURE_HEADER),
        )) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        if ($this->rpcMethod($request) === 'tools/call'
            && ! $this->workspaceIdentified($request)
            && ! $this->isGlobalBuiltin($request)
            && ! $this->agentHasConnectorEnabled($request)) {
            return response()->json([
                'error' => 'This tool is not enabled for this caller.',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Workspace mode: the request carries a signed workspace external_id that
     * resolves to a real workspace owner. The HMAC (already verified above)
     * proves the call came from Chatvia's own server, so naming a valid
     * workspace is sufficient — there is no per-agent connector to check.
     */
    private function workspaceIdentified(Request $request): bool
    {
        $externalId = (string) $request->header('X-Chatvia-Workspace-Id', '');

        if ($externalId === '') {
            return false;
        }

        return User::query()
            ->where('external_id', $externalId)
            ->whereIn('type', UserType::workspaceTypes())
            ->exists();
    }

    /**
     * Global built-in mode: the route is backed by a Chatvia-hosted, always-on
     * built-in (a global `user_id IS NULL`, category `builtin` row — e.g.
     * /mcp/charts). These are ambient for every agent, so the HMAC alone
     * (already verified) is sufficient — there is no per-agent attachment.
     */
    private function isGlobalBuiltin(Request $request): bool
    {
        return AgentMcpServer::query()
            ->whereNull('user_id')
            ->where('category', McpServerCategory::Builtin->value)
            ->where('is_enabled', true)
            ->where('url', 'like', '%/'.$request->path())
            ->exists();
    }

    private function rpcMethod(Request $request): ?string
    {
        $method = $request->json('method');

        return is_string($method) ? $method : null;
    }

    private function agentHasConnectorEnabled(Request $request): bool
    {
        $agentExternalId = (string) $request->header('X-Chatvia-Agent-Id', '');
        if ($agentExternalId === '') {
            return false;
        }

        $agent = Agent::query()->where('external_id', $agentExternalId)->first();
        if (! $agent instanceof Agent) {
            return false;
        }

        // The built-in connector's stored URL ends with this route's path
        // (e.g. .../mcp/weather). It must be attached + enabled on the agent.
        $path = $request->path();

        return AgentMcpServer::query()
            ->where('user_id', $agent->user_id)
            ->where('is_enabled', true)
            ->where('url', 'like', '%/'.$path)
            ->whereExists(function ($q) use ($agent): void {
                $q->from('agent_mcp_server')
                    ->whereColumn('agent_mcp_server.mcp_server_id', 'mcp_servers.id')
                    ->where('agent_mcp_server.agent_id', $agent->id);
            })
            ->exists();
    }
}
