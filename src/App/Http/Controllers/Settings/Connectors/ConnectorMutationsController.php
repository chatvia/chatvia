<?php

namespace App\Http\Controllers\Settings\Connectors;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use Domain\Agents\Data\Resources\AgentMcpServerData;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Agents\Models\McpTool;
use Domain\Chat\Services\Tooling\Mcp\McpCatalog;
use Domain\Chat\Services\Tooling\Mcp\McpException;
use Domain\Connectors\Actions\DeleteMcpServerAction;
use Domain\Connectors\Actions\ToggleMcpServerAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Per-server mutations on an account-level MCP / connector row: toggle
 * enabled, disconnect (delete), refresh catalog.
 */
class ConnectorMutationsController extends Controller
{
    public function __construct(
        private readonly McpCatalog $catalog,
    ) {}

    public function toggle(Request $request, string $slug, ToggleMcpServerAction $toggle): JsonResponse
    {
        $server = $toggle->execute($this->find($request, $slug));

        return response()->json(['data' => AgentMcpServerData::fromModel($server)]);
    }

    public function destroy(Request $request, string $slug, DeleteMcpServerAction $delete): JsonResponse
    {
        $delete->execute($this->find($request, $slug));

        return response()->json(['ok' => true]);
    }

    public function refresh(Request $request, string $slug): JsonResponse
    {
        $server = $this->find($request, $slug);

        try {
            $tools = $this->catalog->refresh($server);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e instanceof McpException ? $e->getMessage() : 'Could not refresh the connector.',
            ], 422);
        }

        return response()->json([
            'data' => AgentMcpServerData::fromModel($server->fresh()),
            'tools_count' => count($tools),
        ]);
    }

    public function tools(Request $request, string $slug): JsonResponse
    {
        $server = $this->find($request, $slug);
        $tools = $server->tools()->orderBy('name')->get();

        $flattened = $tools->map(fn (McpTool $tool): array => [
            'id' => $tool->id,
            'name' => $server->toolPrefix().$tool->name,
            'short_name' => $tool->name,
            'description' => $tool->description,
            'parameters' => $tool->input_schema,
            'enabled' => $tool->enabled,
        ])->all();

        return response()->json([
            'tools' => $flattened,
            'cached_at' => $server->catalog_cached_at,
        ]);
    }

    public function toggleTool(Request $request, string $slug, int $tool): JsonResponse
    {
        $server = $this->find($request, $slug);

        /** @var McpTool|null $row */
        $row = $server->tools()->whereKey($tool)->first();
        abort_unless($row !== null, 404);

        $row->forceFill(['enabled' => ! $row->enabled])->save();

        return response()->json([
            'id' => $row->id,
            'enabled' => (bool) $row->enabled,
        ]);
    }

    private function find(Request $request, string $slug): AgentMcpServer
    {
        /** @var User $user */
        $user = $request->user();
        $server = $user->mcpServers()->where('slug', $slug)->first();

        abort_unless($server !== null, 404);

        return $server;
    }
}
