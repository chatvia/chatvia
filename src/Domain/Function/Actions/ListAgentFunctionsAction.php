<?php

namespace Domain\Function\Actions;

use App\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Function\Data\Resources\AgentFunctionRowData;
use Domain\Function\Data\Resources\AgentFunctionsListResourceData;
use Domain\Function\Models\AgentFunction;
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
