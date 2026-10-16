<?php

namespace Domain\Membership\Agent\Actions;

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Data\Resources\AgentMembershipAssignmentData;
use Domain\Membership\Agent\Data\Resources\AgentMembershipRowData;
use Spatie\LaravelData\DataCollection;

/**
 * Split the workspace's protected agents into "granted to this chatter" and
 * "available to grant" for the Agents tab of a chatter's detail sheet
 * (Settings → Members). Public agents are open to anyone, so per-chatter
 * grants are meaningless and they are left out.
 */
class ListChatterAgentAssignmentAction
{
    public function execute(Chatter $member): AgentMembershipAssignmentData
    {
        $member->loadMissing('workspace');

        $workspaceAgents = $member->workspace->agents()
            ->where('is_public', false)
            ->orderBy('agents.name')
            ->get(['agents.id', 'agents.external_id', 'agents.name']);

        $attachedIds = $member->agents()->pluck('agents.id')->flip();

        [$attached, $available] = $workspaceAgents->partition(
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
