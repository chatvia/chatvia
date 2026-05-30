<?php

namespace Domain\Agents\Actions;

use App\Auth\Models\User;
use Domain\Agents\Data\Resources\AgentFunctionRowData;
use Domain\Agents\Data\Resources\AgentFunctionsListResourceData;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\DataCollection;

/**
 * List every account-level function alongside its attachment state for a
 * specific agent — mirrors the connectors list shape.
 */
class ListAgentFunctionsAction
{
    public function execute(User $user, Agent $agent): AgentFunctionsListResourceData
    {
        $functions = $user->functions()->orderBy('name')->get();

        $attachedIds = DB::table('agent_function')
            ->where('agent_id', $agent->id)
            ->pluck('function_id')
            ->flip();

        $rows = $functions->map(
            fn (AgentFunction $function): AgentFunctionRowData => AgentFunctionRowData::fromModel(
                $function,
                $attachedIds->has($function->id),
            ),
        );

        return new AgentFunctionsListResourceData(
            data: new DataCollection(AgentFunctionRowData::class, $rows),
        );
    }
}
