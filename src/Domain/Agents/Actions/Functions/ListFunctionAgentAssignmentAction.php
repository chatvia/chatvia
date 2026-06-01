<?php

namespace Domain\Agents\Actions\Functions;

use App\Auth\Models\User;
use Domain\Agents\Data\Resources\Members\AgentMembershipAssignmentData;
use Domain\Agents\Data\Resources\Members\AgentMembershipRowData;
use Domain\Agents\Models\AgentFunction;
use Spatie\LaravelData\DataCollection;

/**
 * Bucket every agent in the account into attached / available for a given
 * account-level webhook function. Drives the "Manage agents" sheet on
 * Settings → Functions.
 */
class ListFunctionAgentAssignmentAction
{
    public function execute(User $user, AgentFunction $function): AgentMembershipAssignmentData
    {
        $attachedIds = $function->agents()->pluck('agents.id');

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
