<?php

namespace Domain\Evaluation\Actions;

use App\Identity\Workspace\Actions\MessageLimits\RecordMessageUsageAction;
use App\Identity\Workspace\Services\MessageAllowance;
use Domain\Evaluation\Data\AgentRunResultData;
use Domain\Evaluation\Data\ScoreResultData;
use Domain\Evaluation\Events\EvalCaseScored;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalResult;
use Domain\Evaluation\Models\EvalRun;
use Domain\Evaluation\Services\EvalAgentRunner;
use Domain\Evaluation\Services\Scorers\ScorerRegistry;
use Illuminate\Support\Str;
use Support\Text\Utf8;

/**
 * Runs one eval case through the agent's real pipeline, scores the answer with
 * the case's configured scorer, and writes one immutable {@see EvalResult} row.
 *
 * A failed agent run (or a deleted agent) is recorded as a failing result rather
 * than throwing — so one broken case never aborts the whole batch, and every
 * case in a run is accounted for.
 */
class ScoreEvalCaseAction
{
    public function __construct(
        private readonly EvalAgentRunner $runner,
        private readonly ScorerRegistry $scorers,
        private readonly MessageAllowance $allowance,
        private readonly RecordMessageUsageAction $recordUsage,
    ) {}

    public function execute(EvalRun $run, EvalCase $case): EvalResult
    {
        $agent = $run->agent;

        if ($agent === null) {
            return $this->record(
                $run,
                $case,
                ScoreResultData::fail(0.0, 'Agent no longer exists.'),
                new AgentRunResultData('', [], [], null, 0, (string) Str::uuid(), 'Agent no longer exists.'),
            );
        }

        // The monthly limit can be reached mid-suite, since each case counts
        // as one message, so re-check it before spending an LLM call.
        if (! $this->allowance->canSend($agent->workspace_id)) {
            return $this->recordSkipped($run, $case);
        }

        $result = $this->runner->run($agent, $case->input);

        // A successful run produced one real assistant reply, so it counts
        // against the workspace's limit like a chat reply. An errored run
        // produced no answer, so it does not count.
        if (! $result->failed()) {
            $this->recordUsage->execute($this->allowance->limitFor($agent->workspace_id));
        }

        $score = $result->failed()
            ? ScoreResultData::fail(0.0, 'Agent run failed: '.$result->error)
            : $this->scorers->for($case->scorer_type)->score($case, $result);

        return $this->record($run, $case, $score, $result);
    }

    /**
     * A failing result for a case skipped because the workspace reached its
     * monthly message limit, without running the agent (so no LLM call).
     */
    private function recordSkipped(EvalRun $run, EvalCase $case): EvalResult
    {
        $reason = 'Message limit reached';

        return $this->record(
            $run,
            $case,
            ScoreResultData::fail(0.0, $reason.' — the case was skipped without running the agent.'),
            new AgentRunResultData('', [], [], null, 0, (string) Str::uuid(), $reason.'.'),
        );
    }

    private function record(EvalRun $run, EvalCase $case, ScoreResultData $score, AgentRunResultData $result): EvalResult
    {
        [$output, $truncated] = $this->cap($result->answer, (int) config('chatvia.evals.max_output_bytes', 8192));

        $evalResult = EvalResult::query()->create([
            'run_id' => $run->id,
            'case_id' => $case->id,
            'case_name' => $case->name,
            'scorer_type' => $case->scorer_type,
            'has_passed' => $score->pass,
            'score' => $score->score,
            'actual_output' => $output,
            'actual_output_sha256' => hash('sha256', $result->answer),
            'actual_output_truncated' => $truncated,
            'sources_used' => $result->sources,
            'source_refs' => $result->sourceRefs,
            'tools_called' => $result->toolsCalled,
            'judge_rationale' => $score->rationale,
            'latency_ms' => $result->latencyMs,
            'tokens' => $result->tokens,
            'trace_id' => $result->traceId,
        ]);

        $this->broadcastScored($run, $evalResult);

        return $evalResult;
    }

    /**
     * Push the fresh result + the run's running pass/fail tally to the Evals
     * index and the run drill-down over Reverb. The run's own counts aren't
     * persisted until {@see FinalizeEvalRunAction}, so the tally is derived from
     * the immutable results written so far. Skipped when the agent is gone — the
     * pages that listen live under the agent and are unreachable without it.
     */
    private function broadcastScored(EvalRun $run, EvalResult $result): void
    {
        $agentExternalId = $run->agent?->external_id;

        if ($agentExternalId === null) {
            return;
        }

        // Counted in SQL from the (run_id, has_passed) index, not by loading
        // every result written so far after each case.
        $tally = $run->results()->toBase()
            ->selectRaw('count(*) AS total, count(*) FILTER (WHERE has_passed) AS passed')
            ->first();
        $passed = (int) $tally->passed;

        EvalCaseScored::dispatch(
            $result,
            $run->external_id,
            $agentExternalId,
            $passed,
            (int) $tally->total - $passed,
        );
    }

    /**
     * Byte cap, cut on a character boundary so the stored text stays valid UTF-8.
     *
     * @return array{0: string, 1: bool}
     */
    private function cap(string $text, int $max): array
    {
        return strlen($text) <= $max
            ? [$text, false]
            : [Utf8::truncateBytes($text, $max), true];
    }
}
