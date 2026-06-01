<?php

namespace Domain\Chat\Services\Tooling\Registry;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;
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
    ) {}

    /**
     * @return array{kind: 'mcp'|'function', label: string, icon_url: ?string, brand_color: ?string}
     */
    public function resolve(Agent $agent, string $toolName): array
    {
        if ($this->router->isMcp($toolName)) {
            return $this->resolveMcp($agent, $toolName);
        }

        return $this->resolveFunction($toolName);
    }

    /**
     * @return array{kind: 'mcp', label: string, icon_url: ?string, brand_color: ?string}
     */
    private function resolveMcp(Agent $agent, string $toolName): array
    {
        $rest = substr($toolName, strlen(ToolRouter::MCP_PREFIX));
        $slug = explode('__', $rest, 2)[0];

        $server = AgentMcpServer::query()
            ->where('user_id', $agent->user_id)
            ->where('slug', $slug)
            ->first();

        $connectorSlug = $server?->getAttribute('connector_slug');
        $preset = is_string($connectorSlug) ? Config::get('connectors.'.$connectorSlug) : null;

        $serverName = $server?->getAttribute('name');
        $label = $preset['name'] ?? (is_string($serverName) ? $serverName : $slug);

        return [
            'kind' => 'mcp',
            'label' => $label,
            'icon_url' => $this->iconUrlForPreset($preset),
            'brand_color' => $preset['brand_color'] ?? null,
        ];
    }

    /**
     * @return array{kind: 'function', label: string, icon_url: null, brand_color: null}
     */
    private function resolveFunction(string $toolName): array
    {
        return [
            'kind' => 'function',
            'label' => $toolName,
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
        if (! empty($preset['logo_url'])) {
            return $preset['logo_url'];
        }
        if (! empty($preset['icon'])) {
            $color = $preset['brand_color'] ?? '999999';

            return 'https://cdn.simpleicons.org/'.$preset['icon'].'/'.$color;
        }

        return null;
    }
}
