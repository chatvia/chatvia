<?php

namespace Domain\Integration\Tool\Access;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Data\ResolvedMcpTool;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpServerLookup;
use Domain\Integration\Mcp\Services\McpToolPermissionResolver;
use Domain\Integration\Tool\Data\ToolName;

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
        private readonly McpToolPermissionResolver $permissions,
        private readonly ConnectorCatalog $catalog,
        private readonly McpServerLookup $servers,
    ) {}

    public function requiresApproval(Agent $agent, ToolName $tool): bool
    {
        return $this->memo[$agent->id.'|'.$tool->value] ??= $this->evaluate($agent, $tool);
    }

    private function evaluate(Agent $agent, ToolName $tool): bool
    {
        if (! config('chatvia.agent.tools.approval_enabled', true)) {
            return false;
        }

        return $tool->isMcp()
            ? $this->mcpRequiresApproval($agent, $tool)
            : $this->functionRequiresApproval($agent, $tool);
    }

    private function mcpRequiresApproval(Agent $agent, ToolName $tool): bool
    {
        if (! $tool->hasServerAndTool()) {
            return false;
        }

        $slug = (string) $tool->serverSlug;

        // First-party builtin capabilities (charts, forms, leads) run directly —
        // they are safe, no-auth Chatvia tools and never gate on approval.
        if ($this->catalog->isBuiltinSlug($slug)) {
            return false;
        }

        $server = $this->servers->forWorkspace((int) $agent->user_id, $slug);

        if (! $server instanceof AgentMcpServer) {
            return false;
        }

        $resolved = $this->permissions->resolve($server, $tool->tool);

        // Unknown tool (catalog out of sync) → be safe and gate it.
        if (! $resolved instanceof ResolvedMcpTool) {
            return true;
        }

        return $resolved->permission === McpToolPermission::Approval;
    }

    private function functionRequiresApproval(Agent $agent, ToolName $tool): bool
    {
        $function = AgentFunction::query()
            ->where('user_id', $agent->user_id)
            ->where('internal_name', $tool->value)
            ->first();

        return (bool) ($function?->requires_approval ?? false);
    }
}
