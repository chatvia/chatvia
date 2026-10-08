<?php

namespace Domain\Conversation\Services\Tooling\Registry;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Routing\ToolRouter;
use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Support\Facades\Config;

/**
 * Resolves display metadata for a tool name so the SSE start event can
 * carry a branded label + icon. MCP tools route through the connector
 * preset catalog; function tools fall back to the raw function name.
 */
class ToolDisplayResolver
{
    public function __construct(
        private readonly ToolRouter $router,
        private readonly McpServerLookup $servers,
    ) {}

    /**
     * @return array{kind: 'mcp'|'function', label: string, activity_label: ?string, is_builtin: bool, icon_url: ?string, brand_color: ?string}
     */
    public function resolve(Agent $agent, string $toolName): array
    {
        if ($this->router->isMcp($toolName)) {
            return $this->resolveMcp($agent, $toolName);
        }

        return $this->resolveFunction($agent, $toolName);
    }

    /**
     * @return array{kind: 'mcp', label: string, activity_label: ?string, is_builtin: bool, icon_url: ?string, brand_color: ?string}
     */
    private function resolveMcp(Agent $agent, string $toolName): array
    {
        $rest = substr($toolName, strlen(ToolRouter::MCP_PREFIX));
        $slug = explode('__', $rest, 2)[0];

        $server = $this->servers->forWorkspace((int) $agent->user_id, $slug);

        $connectorSlug = $server?->getAttribute('connector_slug');
        $preset = is_string($connectorSlug) ? Config::get('connectors.'.$connectorSlug) : null;

        $serverName = $server?->getAttribute('name');
        $label = $preset['name'] ?? (is_string($serverName) ? $serverName : $slug);

        // Per-connector transient activity phrase ("Preparing your form…"). The
        // widget shows it verbatim and falls back to "Connecting to {label}…"
        // when absent (e.g. external presets without a configured phrase).
        $activityLabel = isset($preset['activity_label']) && is_string($preset['activity_label'])
            ? $preset['activity_label']
            : null;

        return [
            'kind' => 'mcp',
            'label' => $label,
            'activity_label' => $activityLabel,
            // First-party builtins (forms, charts, leads) are internal plumbing —
            // the client hides their activity indicator. Read straight off the
            // connector preset's server_category.
            'is_builtin' => ($preset['server_category'] ?? null) === 'builtin',
            'icon_url' => $this->iconUrlForPreset($preset),
            'brand_color' => $preset['brand_color'] ?? null,
        ];
    }

    /**
     * @return array{kind: 'function', label: string, activity_label: null, is_builtin: false, icon_url: null, brand_color: null}
     */
    private function resolveFunction(Agent $agent, string $toolName): array
    {
        // Functions are unique per (user_id, name); surface the friendly
        // display_name as the user-facing label when set, else the raw name.
        $displayName = AgentFunction::query()
            ->where('user_id', $agent->user_id)
            ->where('internal_name', $toolName)
            ->value('display_name');

        return [
            'kind' => 'function',
            'label' => is_string($displayName) && $displayName !== '' ? $displayName : $toolName,
            // Webhook functions have no connector preset; the widget derives a
            // generic phrase from the label.
            'activity_label' => null,
            // Custom webhook tools are user-defined, not first-party builtins.
            'is_builtin' => false,
            'icon_url' => null,
            'brand_color' => null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $preset
     */
    private function iconUrlForPreset(?array $preset): ?string
    {
        if ($preset === null) {
            return null;
        }
        // Logos are self-hosted; no third-party icon CDN, so a preset without
        // a logo_url simply shows no icon.
        if (! empty($preset['logo_url'])) {
            return $preset['logo_url'];
        }

        return null;
    }
}
