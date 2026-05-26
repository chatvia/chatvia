<?php

namespace App\Http\Controllers\Agents\Sources\Connectors;

use Domain\Agents\Data\Resources\AgentMcpServerData;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Connectors\Services\ConnectorCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Drives the agent Connectors page. All connector rows live at the
 * account level; this endpoint reports each catalog entry's state for
 * THIS agent:
 *
 *   - `attached`   — account-level row exists AND pivot exists for this agent
 *   - `unattached` — account-level row exists, no pivot for this agent
 *   - `available`  — no account-level row yet (connect it in Settings)
 *
 * In `available` state the agent UI should grey out the toggle — the user
 * has to head to Settings → Connectors to connect first.
 *
 * The Custom section (account-level URL-only connectors with no catalog
 * entry) is returned as `custom`, with per-row attachment state.
 */
class AgentConnectorsListController
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
    ) {}

    public function __invoke(Request $request, Agent $agent): JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $servers = AgentMcpServer::query()
            ->where('user_id', $agent->user_id)
            ->orderBy('created_at')
            ->get();

        $serverBySlug = $servers
            ->whereNotNull('connector_slug')
            ->keyBy('connector_slug');

        $attachedIds = DB::table('agent_mcp_server')
            ->where('agent_id', $agent->id)
            ->pluck('mcp_server_id')
            ->flip();

        $featured = $this->catalog->all()->values()->map(function ($connector) use ($serverBySlug, $attachedIds) {
            /** @var AgentMcpServer|null $row */
            $row = $serverBySlug->get($connector->slug);
            $attached = $row !== null && $attachedIds->has($row->id);

            [$state, $server] = match (true) {
                $attached => ['attached', AgentMcpServerData::fromModel($row)],
                $row !== null => ['unattached', AgentMcpServerData::fromModel($row)],
                default => ['available', null],
            };

            return [
                'connector' => $connector,
                'state' => $state,
                'server' => $server,
            ];
        });

        $custom = $servers
            ->whereNull('connector_slug')
            ->values()
            ->map(fn (AgentMcpServer $s): array => [
                ...AgentMcpServerData::fromModel($s)->toArray(),
                'is_attached' => $attachedIds->has($s->id),
            ]);

        return response()->json([
            'featured' => $featured,
            'custom' => $custom,
        ]);
    }
}
