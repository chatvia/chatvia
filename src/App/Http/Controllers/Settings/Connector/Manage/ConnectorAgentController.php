<?php

namespace App\Http\Controllers\Settings\Connector\Manage;

use App\Http\Controllers\Concerns\ResolvesAccountAgent;
use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Actions\Agent\ListConnectorAgentAssignmentAction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Membership\Agent\Data\Resources\AgentMembershipAssignmentData;
use Illuminate\Http\Request;

/**
 * Manage which of the account's agents use a given connector (MCP server) —
 * lists every account-level agent split into attached vs available. The
 * assignment payload is built by {@see ListConnectorAgentAssignmentAction}.
 */
class ConnectorAgentController
{
    use ResolvesAccountAgent;

    public function index(Request $request, string $slug, ListConnectorAgentAssignmentAction $list): AgentMembershipAssignmentData
    {
        return $list->execute($request->user(), $this->server($request, $slug));
    }

    public function store(
        Request $request,
        string $slug,
        string $agent,
        ListConnectorAgentAssignmentAction $list,
    ): AgentMembershipAssignmentData {
        $server = $this->server($request, $slug);
        $this->resolveAccountAgent($request, $agent)->attachConnector($server);

        return $list->execute($request->user(), $server);
    }

    public function destroy(
        Request $request,
        string $slug,
        string $agent,
        ListConnectorAgentAssignmentAction $list,
    ): AgentMembershipAssignmentData {
        $server = $this->server($request, $slug);
        $this->resolveAccountAgent($request, $agent)->detachConnector($server);

        return $list->execute($request->user(), $server);
    }

    private function server(Request $request, string $slug): AgentMcpServer
    {
        /** @var User $user */
        $user = $request->user();

        return $user->mcpServers()->where('slug', $slug)->firstOrFail();
    }
}
