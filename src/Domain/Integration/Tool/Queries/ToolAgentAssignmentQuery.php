<?php

namespace Domain\Integration\Tool\Queries;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentAccessPolicy;
use Domain\Agent\Models\Agent;
use Domain\Integration\Tool\Contracts\AttachableToAgents;
use Domain\Membership\Agent\Data\Resources\AgentMembershipAssignmentData;
use Domain\Membership\Agent\Data\Resources\AgentMembershipRowData;
use Spatie\LaravelData\DataCollection;

/**
 * Split the agents the viewer may manage into "uses this tool source" and
 * "available" for one MCP server or webhook function. Drives the "Manage
 * agents" sheet on Settings → Connectors and Settings → Functions. Agents
 * outside the viewer's team role are left out, because the attach and detach
 * routes (`can.access.agent`) would refuse them.
 */
class ToolAgentAssignmentQuery
{
    public function __construct(
        private readonly AgentAccessPolicy $access,
    ) {}

    public function get(User $user, AttachableToAgents $source): AgentMembershipAssignmentData
    {
        $attachedIds = $source->agents()->pluck('agents.id')->flip();

        [$attached, $available] = $this->access->manageableAgents($user)->partition(
            fn (Agent $agent): bool => $attachedIds->has($agent->id),
        );

        return new AgentMembershipAssignmentData(
            attached: $this->rows($attached),
            available: $this->rows($available),
        );
    }

    /**
     * @param  iterable<Agent>  $agents
     * @return DataCollection<int, AgentMembershipRowData>
     */
    private function rows(iterable $agents): DataCollection
    {
        $rows = [];
        foreach ($agents as $agent) {
            $rows[] = AgentMembershipRowData::fromModel($agent);
        }

        return new DataCollection(AgentMembershipRowData::class, $rows);
    }
}
