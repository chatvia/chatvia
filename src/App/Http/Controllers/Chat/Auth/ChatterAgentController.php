<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Data\Resources\Chat\ChatAgentRefData;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The agents the signed-in hosted-chat viewer may switch to: a chatter's
 * granted agents, or the agents a staff account can reach.
 */
class ChatterAgentController
{
    /**
     * @return Collection<int, ChatAgentRefData>
     */
    public function __invoke(Request $request): Collection
    {
        $account = $request->user();

        $agents = match (true) {
            $account instanceof Chatter => $account->agents()->get()->toBase(),
            $account instanceof User => $account->listAccessibleAgents(),
            default => collect(),
        };

        return $agents
            ->map(fn (Agent $agent): ChatAgentRefData => ChatAgentRefData::fromModel($agent))
            ->values();
    }
}
