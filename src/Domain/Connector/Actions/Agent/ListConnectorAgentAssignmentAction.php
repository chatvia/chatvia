<?php

namespace Domain\Connector\Actions\Agent;

use App\Workspace\Models\User;
use Domain\Member\Data\Resources\AgentMembershipAssignmentData;
use Domain\Member\Data\Resources\AgentMembershipRowData;
use Domain\Mcp\Models\AgentMcpServer;
use Spatie\LaravelData\DataCollection;

/**
 * Bucket every agent in the account into attached / available for a given
 * connector (MCP server). Drives the "Manage agents" sheet on Settings →
 * Connectors.
 */
class ListConnectorAgentAssignmentAction
{
    public function execute(User $user, AgentMcpServer $server): AgentMembershipAssignmentData
    {
        $attachedIds = $server->agents()->pluck('agents.id');

        $attached = [];
        $available = [];

        foreach ($user->agents as $agent) {
            $row = AgentMembershipRowData::fromModel($agent);

            if ($attachedIds->contains($agent->id)) {
                $attached[] = $row;
            } else {
                $available[] = $row;
            }
        }

        return new AgentMembershipAssignmentData(
            attached: new DataCollection(AgentMembershipRowData::class, $attached),
            available: new DataCollection(AgentMembershipRowData::class, $available),
        );
    }
}
