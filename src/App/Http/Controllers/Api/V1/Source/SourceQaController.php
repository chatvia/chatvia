<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Source;

use App\Http\Requests\Api\V1\Source\StoreSourceQaRequest;
use App\Http\Resources\Api\V1\Source\SourceQaData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Qa\Actions\CreateAgentQaAction;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Curated Q&A entries: exact answers the agent gives word for word when a
 * visitor asks one of the listed questions. Unlike the other source kinds
 * they are matched directly, with no training pipeline to poll. `{qa}` is
 * checked against the agent, so another agent's entry is a 404.
 */
#[Group(name: 'Sources', weight: 70)]
class SourceQaController
{
    /**
     * Add Q&A source
     */
    public function store(StoreSourceQaRequest $request, Agent $agent, CreateAgentQaAction $createQa): JsonResponse
    {
        return response()->json(['data' => SourceQaData::fromModel($createQa->execute($agent, $request->toData()))], 201);
    }

    /**
     * Delete Q&A source
     */
    public function destroy(Agent $agent, AgentQuestionAnswer $qa): Response
    {
        // A single scoped delete — no Action needed (the dashboard inlines it too).
        $qa->delete();

        return response()->noContent();
    }
}
