<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Eval;

use App\Http\Requests\Api\V1\CursorPageRequest;
use App\Http\Resources\Api\V1\Eval\EvalResultData;
use App\Http\Resources\Api\V1\Eval\EvalRunData;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Actions\RunEvalSuiteAction;
use Domain\Evaluation\Models\EvalResult;
use Domain\Evaluation\Models\EvalRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Support\Pagination\CursorPage;

/**
 * Eval-suite runs over the public API: trigger a run and poll its status by
 * id — made for CI pipelines that gate a deploy on the agent's eval pass
 * rate. Triggering reuses the dashboard's {@see RunEvalSuiteAction};
 * `can.access.agent` turns a run of another agent into a 404.
 */
#[Group(name: 'Evals', weight: 120)]
class EvalRunController
{
    /**
     * List eval runs
     *
     * The agent's eval runs, newest first. The list is paged with a cursor:
     * to get the next page, send the `next_cursor` of the response as
     * `cursor`. On the last page `next_cursor` is null.
     *
     * @return array{data: list<EvalRunData>, next_cursor: string|null}
     */
    public function index(CursorPageRequest $request, Agent $agent): array
    {
        $query = EvalRun::query()->forAgent($agent->id);

        $page = CursorPage::newestFirst($query, $request->perPage(), $request->cursor());

        return [
            'data' => array_map(fn (EvalRun $run): EvalRunData => EvalRunData::fromModel($run), $page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }

    /**
     * Trigger eval run
     *
     * Starts a run over the agent's active eval cases. The cases are scored
     * asynchronously: the response is the new run with status `running` (or
     * `completed` right away when the agent has no active cases) — poll
     * `GET …/evals/runs/{id}` until `finished_at` is set. Every scored case
     * counts as one message against the workspace's monthly limit.
     */
    public function store(Request $request, Agent $agent, RunEvalSuiteAction $runEvalSuite): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $run = $runEvalSuite->execute($agent, $user);

        return response()->json(['data' => EvalRunData::fromModel($run)], 201);
    }

    /**
     * Get eval run
     *
     * The run's status and counts, with every per-case result scored so far
     * (oldest first).
     *
     * @return array{data: EvalRunData}
     */
    public function show(Agent $agent, EvalRun $run): array
    {
        $results = $run->results()->orderBy('id')->get()
            ->map(fn (EvalResult $result): EvalResultData => EvalResultData::fromModel($result))
            ->all();

        return ['data' => EvalRunData::fromModel($run, $results)];
    }
}
