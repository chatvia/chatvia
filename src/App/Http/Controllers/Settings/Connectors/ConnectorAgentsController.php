<?php

namespace App\Http\Controllers\Settings\Connectors;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use Domain\Agents\Collections\AgentCollection;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Connectors\Actions\AttachGenericConnectorToAgentAction;
use Domain\Connectors\Actions\DetachGenericConnectorFromAgentAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manage which of the account's agents use a given connector (MCP server),
 * from the Settings → Connectors sheet. Lists every agent in the account,
 * across all teams — connectors are an account-level resource. Returns JSON
 * consumed by the Vue sheet over axios.
 */
class ConnectorAgentsController extends Controller
{
    public function index(Request $request, string $slug): JsonResponse
    {
        return response()->json($this->payload($request, $this->server($request, $slug)));
    }

    public function attach(Request $request, string $slug, string $agent, AttachGenericConnectorToAgentAction $attach): JsonResponse
    {
        $server = $this->server($request, $slug);
        $attach->execute($this->agent($request, $agent), $server);

        return response()->json($this->payload($request, $server));
    }

    public function detach(Request $request, string $slug, string $agent, DetachGenericConnectorFromAgentAction $detach): JsonResponse
    {
        $server = $this->server($request, $slug);
        $detach->execute($this->agent($request, $agent), $server);

        return response()->json($this->payload($request, $server));
    }

    private function server(Request $request, string $slug): AgentMcpServer
    {
        /** @var User $user */
        $user = $request->user();

        return $user->mcpServers()->where('slug', $slug)->firstOrFail();
    }

    private function agent(Request $request, string $externalId): Agent
    {
        $agent = $this->accountAgents($request)->firstWhere('external_id', $externalId);

        abort_if($agent === null, 404);

        return $agent;
    }

    /** Every agent in the account, across all teams — connector assignments are account-level, not team-scoped. */
    private function accountAgents(Request $request): AgentCollection
    {
        /** @var User $user */
        $user = $request->user();

        return $user->agents;
    }

    /**
     * @return array{attached: array<int, array{id: string, name: string}>, available: array<int, array{id: string, name: string}>}
     */
    private function payload(Request $request, AgentMcpServer $server): array
    {
        $attachedIds = $server->agents()->pluck('agents.id');

        $attached = [];
        $available = [];

        foreach ($this->accountAgents($request) as $agent) {
            $row = ['id' => $agent->external_id, 'name' => $agent->name];

            if ($attachedIds->contains($agent->id)) {
                $attached[] = $row;
            } else {
                $available[] = $row;
            }
        }

        return ['attached' => $attached, 'available' => $available];
    }
}
