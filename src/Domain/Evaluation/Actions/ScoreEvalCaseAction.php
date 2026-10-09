<?php

namespace Domain\Evaluation\Actions;

use Domain\Billing\Enums\MessageAdmission;
use Domain\Billing\Jobs\UpdateUserCreditsJob;
use Domain\Billing\Services\MessageSendGate;
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
        private readonly MessageSendGate $sendGate,
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

        // Credits can run dry (or the subscription end) mid-suite, since each
        // case consumes one message, so re-check the same gate live chat uses
        // before spending an LLM call.
        $admission = $this->sendGate->admit($agent->user);

        if (! $admission->allowed()) {
            return $this->recordSkipped($run, $case, $admission);
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
            : $this->scorers->for($case->scorer_type)->score($case, $result);

        return $this->record($run, $case, $score, $result);
    }

    /**
     * A failing result for a case the send gate refused, without running the
     * agent (so no LLM call and no charge).
     */
    private function recordSkipped(EvalRun $run, EvalCase $case, MessageAdmission $admission): EvalResult
    {
        $reason = $admission === MessageAdmission::SubscriptionInactive ? 'Subscription inactive' : 'Message limit reached';

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

        $evalResult = EvalResult::create([
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

        $scored = $run->results()->get(['pass']);
        $passed = $scored->where('pass', true)->count();

        EvalCaseScored::dispatch(
            $result,
            $run->external_id,
            $agentExternalId,
            $passed,
            $scored->count() - $passed,
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
