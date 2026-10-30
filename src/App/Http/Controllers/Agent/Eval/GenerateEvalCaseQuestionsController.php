<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Eval;

use App\Http\Requests\Agent\Eval\GenerateEvalCaseQuestionsRequest;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Actions\GenerateKnowledgeQuestionsAction;
use Illuminate\Http\JsonResponse;

/**
 * The "Generate with AI" button of the Evals "From knowledge" picker: writes
 * a few questions from a random sample of the agent's knowledge and returns
 * them as picker candidates. Nothing is saved; the picker posts the ticked
 * ones to the from-knowledge route. Gated by `agent.evals.manage` on the route.
 */
class GenerateEvalCaseQuestionsController
{
    public function __invoke(GenerateEvalCaseQuestionsRequest $request, Agent $agent, GenerateKnowledgeQuestionsAction $generate): JsonResponse
    {
        return response()->json(['questions' => $generate->execute($agent, $request->desiredCount())]);
    }
}
