<?php

namespace Domain\Integration\Tool\Actions\Assignment;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Tool\Contracts\AttachableToAgents;
use Domain\Membership\Agent\Data\Resources\AgentMembershipAssignmentData;
use Domain\Membership\Agent\Data\Resources\AgentMembershipRowData;
use Spatie\LaravelData\DataCollection;

/**
 * Split every agent of the workspace into "uses this tool source" and
 * "available" for one MCP server or webhook function. Drives the "Manage
 * agents" sheet on Settings → Connectors and Settings → Functions.
 */
class ListToolAgentAssignmentAction
{
    public function execute(User $user, AttachableToAgents $source): AgentMembershipAssignmentData
    {
        $attachedIds = $source->agents()->pluck('agents.id')->flip();

        [$attached, $available] = $user->agents->partition(
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
