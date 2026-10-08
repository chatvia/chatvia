<?php

namespace App\Http\Middleware\Auth;

use App\Identity\Workspace\Models\User;
use Closure;
use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\InternalMcpSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Support\Enums\User\UserType;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates Chatvia's OWN built-in MCP servers (/mcp/weather, /mcp/currency,
 * /mcp/charts, /mcp/forms, /mcp/perplexity, /mcp/account-analytics). These
 * are public HTTP routes but must only ever be driven by Chatvia's
 * server-side tool loop.
 *
 * Two checks:
 *   1. Proof-of-origin — a fresh HMAC attached by McpClient over the method,
 *      path, body and every `X-Chatvia-*` identity header
 *      ({@see InternalMcpSignature}). Rejects anonymous internet callers and
 *      any change to the body or identity headers of a captured request.
 *   2. Authorisation — for a `tools/call`, EITHER:
 *      a. Per-agent enablement: the agent named in the identity header has THIS
 *         built-in connector attached + enabled (so a tool only runs for agents
 *         whose owner explicitly turned it on — charts included); OR
 *      b. Workspace mode: the signed `X-Chatvia-Workspace-Id` names a real
 *         workspace, on one of the servers the owner-facing "Ask your data"
 *         analytics chat uses (config('mcp.builtin')). That chat has no agent
 *         and a fixed internal toolset.
 */
class VerifyInternalMcpRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! InternalMcpSignature::verify(
            $request->header(InternalMcpSignature::TIMESTAMP_HEADER),
            $request->header(InternalMcpSignature::SIGNATURE_HEADER),
            $request->getRealMethod(),
            $request->getBaseUrl().$request->getPathInfo(),
            $request->getContent(),
            InternalMcpSignature::identityHeadersFrom($request->headers->all()),
        )) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        if ($this->rpcMethod($request) === 'tools/call'
            && ! $this->workspaceIdentified($request)
            && ! $this->agentHasConnectorEnabled($request)) {
            return response()->json([
                'error' => 'This tool is not enabled for this caller.',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Workspace mode: the request carries a workspace external_id that
     * resolves to a real workspace owner. The HMAC (already verified above)
     * covers that header and proves the call came from Chatvia's own server,
     * so naming a valid workspace is sufficient — there is no per-agent
     * connector to check. Only the analytics chat's servers accept it.
     */
    private function workspaceIdentified(Request $request): bool
    {
        $externalId = (string) $request->header('X-Chatvia-Workspace-Id', '');

        if ($externalId === '' || ! $this->isAnalyticsChatServer($request)) {
            return false;
        }

        return User::query()
            ->where('external_id', $externalId)
            ->whereIn('type', UserType::workspaceTypes())
            ->exists();
    }

    /**
     * The analytics chat only calls the global built-in servers listed in
     * config('mcp.builtin') (account analytics and charts).
     */
    private function isAnalyticsChatServer(Request $request): bool
    {
        $slugs = array_column((array) config('mcp.builtin', []), 'slug');

        return in_array($request->path(), array_map(static fn (string $slug): string => 'mcp/'.$slug, $slugs), true);
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

        // The built-in connector row for this route (e.g. connector_slug
        // 'weather', URL ending .../mcp/weather) must be attached + enabled on
        // the agent. A custom server that merely points at our URL does not count.
        $path = $request->path();

        return AgentMcpServer::query()
            ->where('user_id', $agent->user_id)
            ->where('is_enabled', true)
            ->where('connector_slug', Str::after($path, 'mcp/'))
            ->where('url', 'like', '%/'.$path)
            ->whereExists(function ($q) use ($agent): void {
                $q->from('agent_mcp_server')
                    ->whereColumn('agent_mcp_server.mcp_server_id', 'mcp_servers.id')
                    ->where('agent_mcp_server.agent_id', $agent->id);
            })
            ->exists();
    }
}
