<?php

namespace App\Http\Controllers\Agents\Sources\Connectors;

use Domain\Agents\Data\Resources\AgentMcpServerData;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Connectors\Services\ConnectorCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Returns the full payload that drives the connectors table:
 *
 *   - `featured` — the preset catalog, each enriched with the agent's
 *     `server` row if one exists.
 *   - `custom`   — every AgentMcpServer the user provisioned without a
 *     `connector_slug` (raw URLs they pasted in).
 */
class AgentConnectorsListController
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
    ) {}

    public function __invoke(Request $request, Agent $agent): JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $servers = $agent->mcpServers()->orderBy('created_at')->get();
        $serversByConnector = $servers->whereNotNull('connector_slug')->keyBy('connector_slug');

        $featured = $this->catalog->all()->values()->map(function ($connector) use ($serversByConnector) {
            $server = $serversByConnector->get($connector->slug);

            return [
                'kind' => 'featured',
                'connector' => $connector,
                'server' => $server ? AgentMcpServerData::fromModel($server) : null,
            ];
        });

        $custom = $servers->whereNull('connector_slug')->values()->map(fn (AgentMcpServer $server): array => [
            'kind' => 'custom',
            'connector' => null,
            'server' => AgentMcpServerData::fromModel($server),
        ]);

        return response()->json([
            'featured' => $featured,
            'custom' => $custom,
        ]);
    }
}
