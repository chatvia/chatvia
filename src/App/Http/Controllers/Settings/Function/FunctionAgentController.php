<?php

namespace App\Http\Controllers\Settings\Function;

use App\Workspace\Models\User;
use App\Http\Controllers\Controller;
use Domain\Agent\Models\Agent;
use Domain\Function\Actions\AttachFunctionToAgentAction;
use Domain\Function\Actions\DetachFunctionFromAgentAction;
use Domain\Function\Actions\ListFunctionAgentAssignmentAction;
use Domain\Function\Models\AgentFunction;
use Domain\Member\Data\Resources\AgentMembershipAssignmentData;
use Illuminate\Http\Request;

/**
 * Manage which of the account's agents use a given account-level webhook
 * function. The assignment payload is built by {@see ListFunctionAgentAssignmentAction}.
 */
class FunctionAgentController extends Controller
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
