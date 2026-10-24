<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Analytics\Unanswered;

use App\Http\Requests\Agent\ChatLog\UnansweredQuestionsRequest;
use Domain\Agent\Models\Agent;
use Domain\Agent\Queries\UnansweredQuestionsQuery;
use Illuminate\Http\JsonResponse;

/**
 * One page of the agent's replies that need a look, newest first.
 */
class AgentUnansweredDataController
{
    public function __invoke(UnansweredQuestionsRequest $request, Agent $agent, UnansweredQuestionsQuery $query): JsonResponse
    {
        return response()->json($query->page($agent, $request->filter(), $request->includeRevised(), $request->page()));
    }
}
