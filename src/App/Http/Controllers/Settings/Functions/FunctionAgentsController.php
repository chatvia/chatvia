<?php

namespace App\Http\Controllers\Settings\Functions;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use Domain\Agents\Actions\AttachFunctionToAgentAction;
use Domain\Agents\Actions\DetachFunctionFromAgentAction;
use Domain\Agents\Actions\ListFunctionAgentAssignmentAction;
use Domain\Agents\Data\Resources\AgentMembershipAssignmentData;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;
use Illuminate\Http\Request;

/**
 * Manage which of the account's agents use a given account-level webhook
 * function. The assignment payload is built by {@see ListFunctionAgentAssignmentAction}.
 */
class FunctionAgentsController extends Controller
{
    public function index(Request $request, string $external_id, ListFunctionAgentAssignmentAction $list): AgentMembershipAssignmentData
    {
        abort_unless(has_team_permission('account.functions'), 404);

        return $list->execute($request->user(), $this->function($request, $external_id));
    }

    public function store(
        Request $request,
        string $external_id,
        string $agent,
        AttachFunctionToAgentAction $attach,
        ListFunctionAgentAssignmentAction $list,
    ): AgentMembershipAssignmentData {
        abort_unless(has_team_permission('account.functions'), 404);

        $function = $this->function($request, $external_id);
        $attach->execute($this->agent($request, $agent), $function);

        return $list->execute($request->user(), $function);
    }

    public function destroy(
        Request $request,
        string $external_id,
        string $agent,
        DetachFunctionFromAgentAction $detach,
        ListFunctionAgentAssignmentAction $list,
    ): AgentMembershipAssignmentData {
        abort_unless(has_team_permission('account.functions'), 404);

        $function = $this->function($request, $external_id);
        $detach->execute($this->agent($request, $agent), $function);

        return $list->execute($request->user(), $function);
    }

    private function function(Request $request, string $externalId): AgentFunction
    {
        /** @var User $user */
        $user = $request->user();

        return $user->functions()->where('external_id', $externalId)->firstOrFail();
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
