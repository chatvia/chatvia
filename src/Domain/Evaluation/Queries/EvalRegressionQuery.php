<?php

declare(strict_types=1);

namespace Domain\Evaluation\Queries;

use Domain\Evaluation\Enums\EvalRunStatus;
use Domain\Evaluation\Models\EvalRun;
use Illuminate\Support\Facades\DB;

/**
 * The cases of a finished run that fail now but passed in the agent's run
 * before it: a change broke an answer that used to be right. Cases new since
 * that run, or deleted, are not counted.
 */
class EvalRegressionQuery
{
    /**
     * @return list<string> the names of the cases that regressed
     */
    public function regressedCases(EvalRun $run): array
    {
        if ($run->agent_id === null) {
            return [];
        }

        $previousRunId = EvalRun::query()
            ->where('agent_id', $run->agent_id)
            ->where('status', EvalRunStatus::Completed)
            ->where('id', '<', $run->id)
            ->latest('id')
            ->value('id');

        if ($previousRunId === null) {
            return [];
        }

        /** @var list<string> */
        return DB::table('eval_results as now')
            ->join('eval_results as before', static function ($join) use ($previousRunId): void {
                $join->on('before.case_id', '=', 'now.case_id')->where('before.run_id', '=', $previousRunId);
            })
            ->where('now.run_id', $run->id)
            ->whereNotNull('now.case_id')
            ->where('now.has_passed', false)
            ->where('before.has_passed', true)
            ->orderBy('now.case_name')
            ->pluck('now.case_name')
            ->all();
    }
}
