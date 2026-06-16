<?php

use Domain\Agent\Models\Agent;
use Domain\Billing\Jobs\UpdateUserCreditsJob;
use Domain\Evaluation\Actions\ScoreEvalCaseAction;
use Domain\Evaluation\Data\AgentRunResultData;
use Domain\Evaluation\Events\EvalCaseScored;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalRun;
use Domain\Evaluation\Services\EvalAgentRunner;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

/**
 * ScoreEvalCase glues the runner to the scorer and writes the immutable result.
 * We fake the runner (the real one needs a live LLM) and assert the mapping:
 * the captured answer/sources/tools/trace land on the row, the scorer's verdict
 * drives pass/score, and a failed agent run becomes a failing result — never an
 * exception that would abort the batch. It also bills the workspace one message
 * credit per case that produced an answer, so fake the bus in every test, and
 * gates on the same can-send-messages flag as live chat — stubbed open here.
 */
beforeEach(function (): void {
    Bus::fake();
    fakeMessageCreditGate();
});

function scoringFixtures(string $value = 'paris'): array
{
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);

    $run = EvalRun::create([
        'agent_id' => $agent->id,
        'agent_name' => $agent->name,
        'workspace_id' => $agent->user_id,
        'status' => EvalRun::STATUS_RUNNING,
        'total_cases' => 1,
        'started_at' => now(),
    ]);

    $case = EvalCase::create([
        'agent_id' => $agent->id,
        'agent_name' => $agent->name,
        'workspace_id' => $agent->user_id,
        'name' => 'capital',
        'input' => 'Capital of France?',
        'scorer_type' => 'contains',
        'scorer_config' => ['value' => $value],
        'active' => true,
    ]);

    return [$run, $case];
}

function fakeRunner(AgentRunResultData $data): EvalAgentRunner
{
    $mock = Mockery::mock(EvalAgentRunner::class);
    $mock->shouldReceive('run')->andReturn($data);

    return $mock;
}

it('records a passing result with the captured run state', function (): void {
    [$run, $case] = scoringFixtures();

    app()->instance(EvalAgentRunner::class, fakeRunner(new AgentRunResultData(
        answer: 'The capital is Paris.',
        sources: ['World Atlas'],
        toolsCalled: ['geo_lookup'],
        tokens: 42,
        latencyMs: 120,
        traceId: 'trace-abc',
    )));

    $result = app(ScoreEvalCaseAction::class)->execute($run, $case);

    expect($result->pass)->toBeTrue()
        ->and($result->score)->toBe(1.0)
        ->and($result->actual_output)->toBe('The capital is Paris.')
        ->and($result->sources_used)->toBe(['World Atlas'])
        ->and($result->tools_called)->toBe(['geo_lookup'])
        ->and($result->tokens)->toBe(42)
        ->and($result->trace_id)->toBe('trace-abc')
        ->and($result->run_id)->toBe($run->id);
});

it('scores a tool_call case from the captured (never executed) tool call', function (): void {
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);

    $run = EvalRun::create([
        'agent_id' => $agent->id,
        'agent_name' => $agent->name,
        'workspace_id' => $agent->user_id,
        'status' => EvalRun::STATUS_RUNNING,
        'total_cases' => 1,
        'started_at' => now(),
    ]);

    $case = EvalCase::create([
        'agent_id' => $agent->id,
        'agent_name' => $agent->name,
        'workspace_id' => $agent->user_id,
        'name' => 'emails support',
        'input' => 'Email the support team.',
        'scorer_type' => 'tool_call',
        'scorer_config' => ['tool' => 'send_email', 'arguments' => '{"to": "support@acme.com"}'],
        'active' => true,
    ]);

    // The runner captures the intended call (extra "subject" arg is ignored) and
    // never executes it — the result carries no text answer, only the tool call.
    app()->instance(EvalAgentRunner::class, fakeRunner(new AgentRunResultData(
        answer: '',
        sources: [],
        toolsCalled: ['send_email'],
        tokens: 12,
        latencyMs: 30,
        traceId: 'trace-tool',
        toolCalls: [['name' => 'send_email', 'arguments' => '{"to": "support@acme.com", "subject": "Help"}']],
    )));

    $result = app(ScoreEvalCaseAction::class)->execute($run, $case);

    expect($result->pass)->toBeTrue()
        ->and($result->scorer_type)->toBe('tool_call')
        ->and($result->tools_called)->toBe(['send_email']);
});

it('records a failing result when the scorer rejects the answer', function (): void {
    [$run, $case] = scoringFixtures('berlin');

    app()->instance(EvalAgentRunner::class, fakeRunner(new AgentRunResultData(
        answer: 'The capital is Paris.',
        sources: [],
        toolsCalled: [],
        tokens: 10,
        latencyMs: 50,
        traceId: 'trace-x',
    )));

    $result = app(ScoreEvalCaseAction::class)->execute($run, $case);

    expect($result->pass)->toBeFalse();
});

it('records a failing result (not an exception) when the agent run errored', function (): void {
    [$run, $case] = scoringFixtures();

    app()->instance(EvalAgentRunner::class, fakeRunner(new AgentRunResultData(
        answer: '',
        sources: [],
        toolsCalled: [],
        tokens: null,
        latencyMs: 0,
        traceId: 'trace-err',
        error: 'model timeout',
    )));

    $result = app(ScoreEvalCaseAction::class)->execute($run, $case);

    expect($result->pass)->toBeFalse()
        ->and($result->judge_rationale)->toContain('Agent run failed')
        ->and($result->judge_rationale)->toContain('model timeout');
});

it('charges one message credit to the workspace for a case that produced an answer', function (): void {
    [$run, $case] = scoringFixtures();
    $agent = $run->agent;

    app()->instance(EvalAgentRunner::class, fakeRunner(new AgentRunResultData(
        answer: 'The capital is Paris.',
        sources: [],
        toolsCalled: [],
        tokens: 42,
        latencyMs: 120,
        traceId: 'trace-bill',
    )));

    app(ScoreEvalCaseAction::class)->execute($run, $case);

    Bus::assertDispatched(
        UpdateUserCreditsJob::class,
        fn (UpdateUserCreditsJob $job): bool => $job->userId === $agent->user_id && $job->modelId === $agent->llm_id,
    );
});

it('does not charge a credit when the agent run errored (no answer produced)', function (): void {
    [$run, $case] = scoringFixtures();

    app()->instance(EvalAgentRunner::class, fakeRunner(new AgentRunResultData(
        answer: '',
        sources: [],
        toolsCalled: [],
        tokens: null,
        latencyMs: 0,
        traceId: 'trace-err',
        error: 'model timeout',
    )));

    app(ScoreEvalCaseAction::class)->execute($run, $case);

    Bus::assertNotDispatched(UpdateUserCreditsJob::class);
});

it('skips the LLM call and charges nothing when the workspace is out of messages', function (): void {
    [$run, $case] = scoringFixtures();

    fakeMessageCreditGate(false);

    // The runner must never be invoked — an out-of-credit case spends no LLM call.
    $runner = Mockery::mock(EvalAgentRunner::class);
    $runner->shouldNotReceive('run');
    app()->instance(EvalAgentRunner::class, $runner);

    $result = app(ScoreEvalCaseAction::class)->execute($run, $case);

    expect($result->pass)->toBeFalse()
        ->and($result->judge_rationale)->toContain('Message limit reached');

    Bus::assertNotDispatched(UpdateUserCreditsJob::class);
});

it('broadcasts the scored case with the run external id and running pass/fail tally', function (): void {
    [$run, $case] = scoringFixtures();
    $agent = $run->agent;

    // Partial fake so HasExternalId's model `creating` hook still assigns the
    // (NOT NULL) external_id to the result row written by the action.
    Event::fake([EvalCaseScored::class]);

    app()->instance(EvalAgentRunner::class, fakeRunner(new AgentRunResultData(
        answer: 'The capital is Paris.',
        sources: [],
        toolsCalled: [],
        tokens: 42,
        latencyMs: 120,
        traceId: 'trace-bc',
    )));

    $result = app(ScoreEvalCaseAction::class)->execute($run, $case);

    Event::assertDispatched(
        EvalCaseScored::class,
        // broadcastWith() in the closure exercises the result resource serialization.
        function (EvalCaseScored $e) use ($run, $agent, $result): bool {
            $payload = $e->broadcastWith();

            return $e->result->is($result)
                && $e->agentExternalId === $agent->external_id
                && $payload['run_external_id'] === $run->external_id
                && $payload['result']['id'] === $result->id
                && $payload['passed_count'] === 1
                && $payload['failed_count'] === 0;
        },
    );
});
