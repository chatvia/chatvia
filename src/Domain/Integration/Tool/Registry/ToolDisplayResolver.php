<?php

namespace Domain\Integration\Tool\Registry;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Services\McpServerLookup;
use Domain\Integration\Tool\Data\ToolDisplayData;
use Domain\Integration\Tool\Data\ToolName;
use Domain\Integration\Tool\Enums\ToolKind;

/**
 * Resolves display metadata for a tool name so the SSE start event can
 * carry a branded label + icon. MCP tools route through the connector
 * preset catalog; function tools fall back to the raw function name.
 */
class ToolDisplayResolver
{
    public function __construct(
        private readonly McpServerLookup $servers,
        private readonly ConnectorCatalog $catalog,
    ) {}

    public function resolve(Agent $agent, ToolName $tool): ToolDisplayData
    {
        return $tool->isMcp()
            ? $this->resolveMcp($agent, $tool)
            : $this->resolveFunction($agent, $tool);
    }

    private function resolveMcp(Agent $agent, ToolName $tool): ToolDisplayData
    {
        $slug = $tool->serverSlug ?? '';
        $server = $this->servers->forWorkspace((int) $agent->workspace_id, $slug);

        $connectorSlug = $server?->getAttribute('connector_slug');
        $preset = filled($connectorSlug) ? $this->catalog->find($connectorSlug) : null;

        $serverName = $server?->getAttribute('name');

        return new ToolDisplayData(
            kind: ToolKind::Mcp,
            label: $preset?->name ?? (filled($serverName) ? $serverName : $slug),
            // Per-connector transient activity phrase ("Preparing your form…").
            // The widget shows it verbatim and falls back to "Connecting to
            // {label}…" when absent (e.g. external presets without a phrase).
            activityLabel: $preset?->activityLabel,
            // First-party builtins (forms, charts, leads) are internal plumbing —
            // the client hides their activity indicator. Read straight off the
            // connector's server category.
            isBuiltin: $preset?->serverCategory() === McpServerCategory::Builtin,
            // Logos are self-hosted; no third-party icon CDN.
            iconUrl: $preset?->logoUrl,
            brandColor: $preset?->brandColor,
        );
    }

    private function resolveFunction(Agent $agent, ToolName $tool): ToolDisplayData
    {
        // Functions are unique per (workspace_id, name); surface the friendly
        // display_name as the user-facing label when set, else the raw name.
        $displayName = AgentFunction::query()
            ->where('workspace_id', $agent->workspace_id)
            ->where('internal_name', $tool->value)
            ->value('display_name');

        // Webhook functions have no connector preset (the widget derives a
        // generic activity phrase from the label) and are user-defined, never
        // first-party builtins.
        return new ToolDisplayData(
            kind: ToolKind::Function,
            label: filled($displayName) ? $displayName : $tool->value,
        );
    }
}
