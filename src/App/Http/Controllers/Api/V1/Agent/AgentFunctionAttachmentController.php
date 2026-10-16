<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Requests\Api\V1\Agent\AttachAgentFunctionRequest;
use App\Http\Requests\Api\V1\CursorPageRequest;
use App\Http\Resources\Api\V1\Tool\FunctionData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Support\Pagination\CursorPage;

/**
 * Attach / detach account-level functions to a specific agent.
 * `can.access.agent` turns a function of another workspace into a 404.
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
            'data' => array_map(FunctionData::fromModel(...), $page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }

    /**
     * Attach function to agent
     */
    public function store(AttachAgentFunctionRequest $request, Agent $agent): JsonResponse
    {
        $data = $request->toData($agent);

        $data->agent->attachFunction($data->function);

        return response()->json(['data' => FunctionData::fromModel($data->function)]);
    }

    /**
     * Detach function from agent
     */
    public function destroy(Agent $agent, AgentFunction $function): Response
    {
        $agent->detachFunction($function);

        return response()->noContent();
    }
}
