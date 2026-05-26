<?php

namespace App\Http\Controllers\Settings\Connectors;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use Domain\Agents\Data\Resources\AgentMcpServerData;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Connectors\Services\ConnectorCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the Settings → Connectors page and feeds it the {featured, custom}
 * payload — the account-level analogue of the per-agent Connectors page.
 */
class ConnectorsController extends Controller
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('settings/Connectors');
    }

    /**
     * JSON endpoint used by the Vue page to fetch (and refresh) the rows.
     */
    public function data(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $servers = $user->mcpServers()->orderBy('created_at')->get();
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
