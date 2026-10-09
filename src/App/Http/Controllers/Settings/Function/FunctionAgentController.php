<?php

namespace App\Http\Controllers\Settings\Function;

use App\Http\Controllers\Concerns\ResolvesAccountAgent;
use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Tool\Actions\Assignment\ListToolAgentAssignmentAction;
use Domain\Membership\Agent\Data\Resources\AgentMembershipAssignmentData;
use Illuminate\Http\Request;

/**
 * Manage which of the workspace's agents use a given webhook function: list
 * every agent split into attached vs available, attach one, detach one. Each
 * call answers with the updated split.
 */
class FunctionAgentController
{
    use ResolvesAccountAgent;

    public function index(Request $request, string $external_id, ListToolAgentAssignmentAction $list): AgentMembershipAssignmentData
    {
        return $list->execute($request->user(), $this->function($request, $external_id));
    }

    public function store(Request $request, string $external_id, string $agent, ListToolAgentAssignmentAction $list): AgentMembershipAssignmentData
    {
        $function = $this->function($request, $external_id);
        $this->resolveAccountAgent($request, $agent)->attachFunction($function);

        return $list->execute($request->user(), $function);
    }

    public function destroy(Request $request, string $external_id, string $agent, ListToolAgentAssignmentAction $list): AgentMembershipAssignmentData
    {
        $function = $this->function($request, $external_id);
        $this->resolveAccountAgent($request, $agent)->detachFunction($function);

        return $list->execute($request->user(), $function);
    }

    private function function(Request $request, string $externalId): AgentFunction
    {
        /** @var User $user */
        $user = $request->user();

        return $user->workspace()->functions()->where('external_id', $externalId)->firstOrFail();
    }
}
