<?php

namespace App\Http\Middleware\Auth;

use App\Mcp\McpCallerContext;
use App\Mcp\McpCallerResolver;
use Closure;
use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\InternalMcpSignature;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
 *
 * An authorised `tools/call` also binds the resolved {@see McpCallerContext}
 * for the rest of the request, so the tool reads its caller from there.
 */
class VerifyInternalMcpRequest
{
    public function __construct(
        private readonly McpCallerResolver $callers,
        private readonly Container $container,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->hasValidSignature($request)) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        // Only a tools/call runs a tool; the handshake and tool listing need no caller.
        if ($this->rpcMethod($request) !== 'tools/call') {
            return $next($request);
        }

        $caller = $this->callers->resolve($request);

        if ($caller->workspace === null && ! $this->agentHasConnectorEnabled($request, $caller->agent)) {
            return response()->json([
                'error' => 'This tool is not enabled for this caller.',
            ], 403);
        }

        $this->container->instance(McpCallerContext::class, $caller);

        return $next($request);
    }

    private function hasValidSignature(Request $request): bool
    {
        return InternalMcpSignature::verify(
            $request->header(InternalMcpSignature::TIMESTAMP_HEADER),
            $request->header(InternalMcpSignature::SIGNATURE_HEADER),
            $request->getRealMethod(),
            $request->getBaseUrl().$request->getPathInfo(),
            $request->getContent(),
            InternalMcpSignature::identityHeadersFrom($request->headers->all()),
        );
    }

    private function rpcMethod(Request $request): ?string
    {
        $method = $request->json('method');

        return is_string($method) ? $method : null;
    }

    private function agentHasConnectorEnabled(Request $request, ?Agent $agent): bool
    {
        if ($agent === null) {
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
