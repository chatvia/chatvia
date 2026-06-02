<?php

namespace App\Http\Controllers\Settings\Connector\Manage;

use App\Workspace\Models\User;
use App\Http\Controllers\Controller;
use Domain\Member\Data\Resources\AgentMembershipAssignmentData;
use Domain\Agent\Models\Agent;
use Domain\Mcp\Models\AgentMcpServer;
use Domain\Connector\Actions\Agent\AttachGenericConnectorToAgentAction;
use Domain\Connector\Actions\Agent\DetachGenericConnectorFromAgentAction;
use Domain\Connector\Actions\Agent\ListConnectorAgentAssignmentAction;
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
        AttachGenericConnectorToAgentAction $attach,
        ListConnectorAgentAssignmentAction $list,
    ): AgentMembershipAssignmentData {
        abort_unless(has_team_permission('account.connectors'), 404);

        $server = $this->server($request, $slug);
        $attach->execute($this->agent($request, $agent), $server);

        return $list->execute($request->user(), $server);
    }

    public function destroy(
        Request $request,
        string $slug,
        string $agent,
        DetachGenericConnectorFromAgentAction $detach,
        ListConnectorAgentAssignmentAction $list,
    ): AgentMembershipAssignmentData {
        abort_unless(has_team_permission('account.connectors'), 404);

        $server = $this->server($request, $slug);
        $detach->execute($this->agent($request, $agent), $server);

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
