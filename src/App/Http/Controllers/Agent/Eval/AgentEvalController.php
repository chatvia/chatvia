<?php

namespace App\Http\Controllers\Agent\Eval;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Actions\ListKnowledgeQuestionCandidatesAction;
use Domain\Evaluation\Data\Resources\EvalCaseResourceData;
use Domain\Evaluation\Data\Resources\EvalRunResourceData;
use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalRun;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-agent Eval Suites page. Lists the agent's eval cases plus its recent
 * runs; the page lets a teammate with
 * `agent.evals.manage` edit cases and trigger a run, while `agent.evals.view`
 * grants read-only access.
 */
class AgentEvalController
{
    public function __invoke(
        Agent $agent,
        ListKnowledgeQuestionCandidatesAction $knowledgeQuestions,
    ): Response {
        $cases = EvalCase::query()->forAgent($agent->id)->orderByDesc('id')->get();
        $runs = EvalRun::query()->forAgent($agent->id)->with('triggeredByUser')->orderByDesc('id')->limit(20)->get();

        return Inertia::render('agents/Evals/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'cases' => EvalCaseResourceData::collection($cases),
            'runs' => EvalRunResourceData::collection($runs),
            'scorerTypes' => array_column(ScorerType::cases(), 'value'),
            // Optional: only evaluated on the partial reload fired when the
            // "From knowledge" picker opens — mining questions decrypts up to
            // 200 chunk rows, too costly for first load / the runs polling.
            'knowledgeQuestions' => Inertia::optional(fn (): array => $knowledgeQuestions->execute($agent)),
        ]);
    }
}
