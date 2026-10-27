<?php

namespace App\Http\Controllers\Settings\Connector\Manage;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Queries\ToolAgentAssignmentQuery;
use Domain\Membership\Agent\Data\Resources\AgentMembershipAssignmentData;
use Illuminate\Http\Request;

/**
 * Manage which of the workspace's agents use a given connector (MCP server):
 * list the agents the viewer may manage, split into attached vs available,
 * attach one, detach one. Each call answers with the updated split. The
 * attach and detach routes carry `can.access.agent`, so a teammate can only
 * wire a connector to an agent their team role reaches.
 */
class ConnectorAgentController
{
    public function index(Request $request, string $slug, ToolAgentAssignmentQuery $list): AgentMembershipAssignmentData
    {
        return $list->get($request->user(), $this->server($request, $slug));
    }

    public function store(Request $request, string $slug, Agent $agent, ToolAgentAssignmentQuery $list): AgentMembershipAssignmentData
    {
        $server = $this->server($request, $slug);
        $agent->attachConnector($server);

        return $list->get($request->user(), $server);
    }

    public function destroy(Request $request, string $slug, Agent $agent, ToolAgentAssignmentQuery $list): AgentMembershipAssignmentData
    {
        $server = $this->server($request, $slug);
        $agent->detachConnector($server);

        return $list->get($request->user(), $server);
    }

    private function server(Request $request, string $slug): AgentMcpServer
    {
        /** @var User $user */
        $user = $request->user();

        return AgentMcpServer::forSlugScoped($slug, $user);
    }
}
