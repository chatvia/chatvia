<?php

namespace App\Http\Controllers\Settings\Connector\Manage;

use App\Http\Controllers\Controller;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Actions\Agent\ListConnectorAgentAssignmentAction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Membership\Agent\Data\Resources\AgentMembershipAssignmentData;
use Illuminate\Http\Request;

/**
 * Manage which of the account's agents use a given connector (MCP server) —
 * lists every account-level agent split into attached vs available. The
 * assignment payload is built by {@see ListConnectorAgentAssignmentAction}.
 */
class ConnectorAgentController extends Controller
{
    public function index(Request $request, string $slug, ListConnectorAgentAssignmentAction $list): AgentMembershipAssignmentData
    {
        abort_unless(has_team_permission('account.connectors'), 404);

        return $list->execute($request->user(), $this->server($request, $slug));
    }

    public function store(
        Request $request,
        string $slug,
        string $agent,
        ListConnectorAgentAssignmentAction $list,
    ): AgentMembershipAssignmentData {
        abort_unless(has_team_permission('account.connectors'), 404);

        $server = $this->server($request, $slug);
        $this->agent($request, $agent)->attachConnector($server);

        return $list->execute($request->user(), $server);
    }

    public function destroy(
        Request $request,
        string $slug,
        string $agent,
        ListConnectorAgentAssignmentAction $list,
    ): AgentMembershipAssignmentData {
        abort_unless(has_team_permission('account.connectors'), 404);

        $server = $this->server($request, $slug);
        $this->agent($request, $agent)->detachConnector($server);

        return $list->execute($request->user(), $server);
    }

    private function server(Request $request, string $slug): AgentMcpServer
    {
        /** @var User $user */
        $user = $request->user();

        return $user->mcpServers()->where('slug', $slug)->firstOrFail();
    }

    private function agent(Request $request, string $externalId): Agent
    {
        /** @var User $user */
        $user = $request->user();
        $agent = $user->agents->firstWhere('external_id', $externalId);

        abort_if($agent === null, 404);

        return $agent;
    }
}
