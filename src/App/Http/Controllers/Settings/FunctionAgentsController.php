<?php

namespace App\Http\Controllers\Settings;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use App\Teams\Services\TeamService;
use Domain\Agents\Actions\AttachFunctionToAgentAction;
use Domain\Agents\Actions\DetachFunctionFromAgentAction;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manage which of the account's agents use a given function, from the
 * account-level Settings → Functions sheet. Scoped to the current team's
 * accessible agents. Returns JSON consumed by the Vue sheet over axios.
 */
class FunctionAgentsController extends Controller
{
    public function __construct(private readonly TeamService $teams) {}

    public function index(Request $request, string $external_id): JsonResponse
    {
        return response()->json($this->payload($request, $this->function($request, $external_id)));
    }

    public function attach(Request $request, string $external_id, string $agent, AttachFunctionToAgentAction $attach): JsonResponse
    {
        $function = $this->function($request, $external_id);
        $attach->execute($this->agent($request, $agent), $function);

        return response()->json($this->payload($request, $function));
    }

    public function detach(Request $request, string $external_id, string $agent, DetachFunctionFromAgentAction $detach): JsonResponse
    {
        $function = $this->function($request, $external_id);
        $detach->execute($this->agent($request, $agent), $function);

        return response()->json($this->payload($request, $function));
    }

    private function function(Request $request, string $externalId): AgentFunction
    {
        /** @var User $user */
        $user = $request->user();

        return $user->functions()->where('external_id', $externalId)->firstOrFail();
    }

    private function agent(Request $request, string $externalId): Agent
    {
        $agent = $this->accessibleAgents($request)->firstWhere('external_id', $externalId);

        abort_if($agent === null, 404);

        return $agent;
    }

    private function accessibleAgents(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $team = current_team();

        abort_if($team === null, 404);

        return $this->teams->accessibleAgents($user, $team);
    }

    /**
     * @return array{attached: array<int, array{id: string, name: string}>, available: array<int, array{id: string, name: string}>}
     */
    private function payload(Request $request, AgentFunction $function): array
    {
        $attachedIds = $function->agents()->pluck('agents.id');

        $attached = [];
        $available = [];

        foreach ($this->accessibleAgents($request) as $agent) {
            $row = ['id' => $agent->external_id, 'name' => $agent->name];

            if ($attachedIds->contains($agent->id)) {
                $attached[] = $row;
            } else {
                $available[] = $row;
            }
        }

        return ['attached' => $attached, 'available' => $available];
    }
}
