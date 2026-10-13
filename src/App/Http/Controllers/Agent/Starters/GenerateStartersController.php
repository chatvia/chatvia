<?php

namespace App\Http\Controllers\Agent\Starters;

use App\Http\Requests\Agent\Starters\GenerateStartersRequest;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Llm\Operations\Starters\StarterGenerator;
use Illuminate\Http\JsonResponse;

/**
 * Suggests conversation starters by scanning the agent's knowledge base. A
 * read-only suggest operation (nothing is persisted — the owner picks which
 * suggestions to add in the Setup UI), so it stays a thin controller: sample
 * the agent's chunks and hand them to the generator service.
 */
class GenerateStartersController
{
    public function __invoke(GenerateStartersRequest $request, Agent $agent, StarterGenerator $generator): JsonResponse
    {
        $content = Chunk::query()->contentSampleForAgent($agent);

        $suggestions = $generator->generate($content, $request->desiredCount());

        return response()->json(['suggestions' => $suggestions]);
    }
}
