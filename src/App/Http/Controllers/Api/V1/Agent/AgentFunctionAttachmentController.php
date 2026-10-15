<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Requests\Api\V1\Agent\AttachAgentFunctionRequest;
use App\Http\Requests\Api\V1\CursorPageRequest;
use App\Http\Resources\Api\V1\Tools\FunctionData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Http\JsonResponse;
use Support\Pagination\CursorPage;

/**
 * Attach / detach account-level functions to a specific agent.
 */
#[Group(name: 'Functions', weight: 60)]
class AgentFunctionAttachmentController
{
    /**
     * List an agent's functions
     *
     * The functions attached to the agent, newest first. The list is paged
     * with a cursor: to get the next page, send the `next_cursor` of the
     * response as `cursor`. On the last page `next_cursor` is null.
     *
     * @return array{data: list<FunctionData>, next_cursor: string|null}
     */
    public function index(CursorPageRequest $request, Agent $agent): array
    {
        $page = CursorPage::newestFirst($agent->functions(), $request->perPage(), $request->cursor());

        return [
            'data' => array_map(fn (AgentFunction $f): FunctionData => FunctionData::fromModel($f), $page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }

    /**
     * Attach function to agent
     */
    public function store(AttachAgentFunctionRequest $request, Agent $agent): JsonResponse
    {
        $data = $request->toData($agent);
        $function = $this->resolveFunction($data->agent, $data->function_id);

        $data->agent->attachFunction($function);

        return response()->json(['data' => FunctionData::fromModel($function->fresh())], 201);
    }

    /**
     * Detach function from agent
     */
    public function destroy(Agent $agent, string $function): JsonResponse
    {
        $agent->detachFunction($this->resolveFunction($agent, $function));

        return response()->json(null, 204);
    }

    private function resolveFunction(Agent $agent, string $externalId): AgentFunction
    {
        return AgentFunction::query()
            ->where('workspace_id', $agent->workspace_id)
            ->where('external_id', $externalId)
            ->firstOrFail();
    }
}
