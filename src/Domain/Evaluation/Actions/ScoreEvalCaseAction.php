<?php

namespace Domain\Evaluation\Actions;

use Domain\Billing\Jobs\UpdateUserCreditsJob;
use Domain\Billing\Services\UserCreditSystem;
use Domain\Evaluation\Data\AgentRunResultData;
use Domain\Evaluation\Data\ScoreResultData;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalResult;
use Domain\Evaluation\Models\EvalRun;
use Domain\Evaluation\Services\EvalAgentRunner;
use Domain\Evaluation\Services\Scorers\ScorerRegistry;
use Illuminate\Support\Str;

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
        private readonly UserCreditSystem $creditSystem,
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

        // Credits can run dry mid-suite (each case consumes one message), so
        // re-check the same gate live chat uses before spending an LLM call.
        if (! $this->creditSystem->forWorkspace($agent->user_id)->canSendMessages()) {
            return $this->record(
                $run,
                $case,
                ScoreResultData::fail(0.0, 'Message limit reached — the case was skipped without running the agent.'),
                new AgentRunResultData('', [], [], null, 0, (string) Str::uuid(), 'Message limit reached.'),
            );
        }

        $result = $this->runner->run($agent, $case->input);

        // Per-answer billing: a successful run produced one real assistant reply,
        // so charge the workspace the model's normal message cost — the same call
        // the live chat path makes in ChatService::handleChatCompletion. An errored
        // run produced no answer, so it costs nothing.
        if (! $result->failed() && $agent->llm_id !== null) {
            UpdateUserCreditsJob::dispatch(
                userId: $agent->user_id,
                modelId: $agent->llm_id,
            );
        }

        $score = $result->failed()
            ? ScoreResultData::fail(0.0, 'Agent run failed: '.$result->error)
            : $this->scorers->for($case->scorer_type)->score($case, $result->answer);

        return $this->record($run, $case, $score, $result);
    }

    private function record(EvalRun $run, EvalCase $case, ScoreResultData $score, AgentRunResultData $result): EvalResult
    {
        [$output, $truncated] = $this->cap($result->answer, (int) config('chatvia.evals.max_output_bytes', 8192));

        return EvalResult::create([
            'run_id' => $run->id,
            'case_id' => $case->id,
            'case_name' => $case->name,
            'scorer_type' => $case->scorer_type->value,
            'pass' => $score->pass,
            'score' => $score->score,
            'actual_output' => $output,
            'actual_output_sha256' => hash('sha256', $result->answer),
            'actual_output_truncated' => $truncated,
            'sources_used' => $result->sources,
            'tools_called' => $result->toolsCalled,
            'judge_rationale' => $score->rationale,
            'latency_ms' => $result->latencyMs,
            'tokens' => $result->tokens,
            'trace_id' => $result->traceId,
        ]);
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function cap(string $text, int $max): array
    {
        return strlen($text) <= $max
            ? [$text, false]
            : [substr($text, 0, $max), true];
    }
}
