<?php

namespace App\Http\Controllers\Settings\Function;

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Tool\Actions\Assignment\ListToolAgentAssignmentAction;
use Domain\Membership\Agent\Data\Resources\AgentMembershipAssignmentData;
use Illuminate\Http\Request;

/**
 * Manage which of the workspace's agents use a given webhook function: list
 * the agents the viewer may manage, split into attached vs available, attach
 * one, detach one. Each call answers with the updated split. The route binds
 * `{function}` and `{agent}`; `workspace.scope` 404s another workspace's, and
 * `can.access.agent` on attach and detach refuses an agent outside the
 * viewer's team role.
 */
class FunctionAgentController
{
    public function index(Request $request, AgentFunction $function, ListToolAgentAssignmentAction $list): AgentMembershipAssignmentData
    {
        return $list->execute($request->user(), $function);
    }

    public function store(Request $request, AgentFunction $function, Agent $agent, ListToolAgentAssignmentAction $list): AgentMembershipAssignmentData
    {
        $agent->attachFunction($function);

        return $list->execute($request->user(), $function);
    }

    public function destroy(Request $request, AgentFunction $function, Agent $agent, ListToolAgentAssignmentAction $list): AgentMembershipAssignmentData
    {
        $agent->detachFunction($function);

        return $list->execute($request->user(), $function);
    }
}
