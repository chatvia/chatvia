<?php

namespace Domain\Conversation\Services\Tooling\Approval;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Registry\McpServerLookup;
use Domain\Conversation\Services\Tooling\Routing\ToolRouter;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Services\McpToolPermissionResolver;

/**
 * Decides whether a given tool call must pause for human approval before it
 * runs. The decision reads stored values only:
 *
 *   - MCP tool  → mcp_tools.permission === Approval
 *   - function  → function.requires_approval
 *
 * The write/destructive heuristic (annotations / webhook_method) is applied
 * once, at catalog/create time, to seed those values — not here. A global
 * kill-switch (`agent.tools.approval_enabled`) short-circuits everything.
 *
 * Resolution mirrors the dispatchers so the policy gates exactly the calls the
 * dispatcher would run: MCP by `(user_id, slug)` + `(mcp_server_id, name)`,
 * functions by `(user_id, internal_name)`.
 */
class ToolApprovalPolicy
{
    /**
     * Per-request memo so repeated checks for the same tool (e.g. across tool-
     * loop iterations) don't re-hit the DB. The policy is resolved once per
     * chat request, so this never leaks across conversations.
     *
     * @var array<string, bool>
     */
    private array $memo = [];

    public function __construct(
        private readonly ToolRouter $router,
        private readonly McpToolPermissionResolver $permissions,
        private readonly ConnectorCatalog $catalog,
        private readonly McpServerLookup $servers,
    ) {}

    public function requiresApproval(Agent $agent, string $toolName): bool
    {
        return $this->memo[$agent->id.'|'.$toolName] ??= $this->evaluate($agent, $toolName);
    }

    private function evaluate(Agent $agent, string $toolName): bool
    {
        if (! config('chatvia.agent.tools.approval_enabled', true)) {
            return false;
        }

        return $this->router->isMcp($toolName)
            ? $this->mcpRequiresApproval($agent, $toolName)
            : $this->functionRequiresApproval($agent, $toolName);
    }

    private function mcpRequiresApproval(Agent $agent, string $toolName): bool
    {
        [$slug, $tool] = $this->parsePrefixedName($toolName);
        if ($slug === null || $tool === null) {
            return false;
        }

        // First-party builtin capabilities (charts, forms, leads) run directly —
        // they are safe, no-auth Chatvia tools and never gate on approval.
        if ($this->catalog->isBuiltinSlug($slug)) {
            return false;
        }

        $server = $this->servers->forWorkspace((int) $agent->user_id, $slug);

        if (! $server) {
            return false;
        }

        $resolved = $this->permissions->resolve($server, $tool);

        // Unknown tool (catalog out of sync) → be safe and gate it.
        if ($resolved === null) {
            return true;
        }

        return $resolved->permission === McpToolPermission::Approval;
    }

    private function functionRequiresApproval(Agent $agent, string $name): bool
    {
        $function = AgentFunction::query()
            ->where('user_id', $agent->user_id)
            ->where('internal_name', $name)
            ->first();

        return (bool) ($function?->requires_approval ?? false);
    }

    /**
     * Split `mcp__{slug}__{tool}` into `[slug, tool]`. Mirrors
     * McpToolDispatcher::parsePrefixedName so resolution stays identical.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function parsePrefixedName(string $name): array
    {
        if (! str_starts_with($name, ToolRouter::MCP_PREFIX)) {
            return [null, null];
        }

        $rest = substr($name, strlen(ToolRouter::MCP_PREFIX));
        $pos = strpos($rest, '__');
        if ($pos === false) {
            return [null, null];
        }

        $slug = substr($rest, 0, $pos);
        $tool = substr($rest, $pos + 2);

        return [$slug !== '' ? $slug : null, $tool !== '' ? $tool : null];
    }
}
