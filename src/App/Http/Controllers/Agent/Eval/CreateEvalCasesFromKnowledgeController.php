<?php

namespace App\Http\Controllers\Agent\Eval;

use App\Http\Requests\Agent\Eval\CreateEvalCasesFromKnowledgeRequest;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Actions\CreateEvalCasesFromQuestionsAction;
use Illuminate\Http\RedirectResponse;

/**
 * Bulk-creates eval cases from the knowledge-base questions selected in the
 * "From knowledge" picker. Gated by `agent.evals.manage` on the route.
 */
class CreateEvalCasesFromKnowledgeController
{
    public function __construct(
        private readonly CreateEvalCasesFromQuestionsAction $createEvalCases,
    ) {}

    public function __invoke(CreateEvalCasesFromKnowledgeRequest $request, Agent $agent): RedirectResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $this->createEvalCases->execute($request->toData(), $agent);

        return back();
    }
}
