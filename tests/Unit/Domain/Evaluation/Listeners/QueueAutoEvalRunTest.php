<?php

use Domain\Agent\Events\AgentAnswersChanged;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Jobs\RunAutoEvalJob;
use Domain\Evaluation\Listeners\QueueAutoEvalRun;
use Domain\Evaluation\Models\EvalCase;
use Domain\Knowledge\Ingestion\Events\SourceTrained;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\Queue;

/**
 * With "Run evals after changes" on, a change to the agent's answers queues
 * one eval run a while later; with it off, or with no active case, nothing.
 */
beforeEach(function (): void {
    Queue::fake();
    $this->agent = Agent::factory()->create();
});

it('queues a delayed run when the agent changed and the switch is on', function (): void {
    $this->agent->setSetting('auto_run', true, 'evals');
    EvalCase::factory()->forAgent($this->agent)->create();

    app(QueueAutoEvalRun::class)->handle(new AgentAnswersChanged($this->agent->id));

    Queue::assertPushed(RunAutoEvalJob::class, fn (RunAutoEvalJob $job): bool => $job->agentId === $this->agent->id
        && $job->delay === RunAutoEvalJob::DELAY_SECONDS);
});

it('queues a run when a source finished training', function (): void {
    $this->agent->setSetting('auto_run', true, 'evals');
    EvalCase::factory()->forAgent($this->agent)->create();
    $source = SourceText::factory()->create(['workspace_id' => $this->agent->workspace_id]);

    app(QueueAutoEvalRun::class)->handle(new SourceTrained($source, $this->agent->external_id));

    Queue::assertPushed(RunAutoEvalJob::class);
});

it('queues nothing while the switch is off', function (): void {
    EvalCase::factory()->forAgent($this->agent)->create();

    app(QueueAutoEvalRun::class)->handle(new AgentAnswersChanged($this->agent->id));

    Queue::assertNothingPushed();
});

it('queues nothing for an agent without an active case', function (): void {
    $this->agent->setSetting('auto_run', true, 'evals');
    EvalCase::factory()->forAgent($this->agent)->inactive()->create();

    app(QueueAutoEvalRun::class)->handle(new AgentAnswersChanged($this->agent->id));

    Queue::assertNothingPushed();
});
