<?php

use App\Admin\Issue\Jobs\RecordIssueJob;
use App\Admin\Issue\Models\Issue;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\LlmProviders;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Queue;
use Support\Radar\IssueRaised;
use Support\Radar\IssueTypeKey;
use Support\Radar\ServiceKey;
use Symfony\Component\Mailer\Exception\TransportException;

/**
 * Issues raised from anywhere reach Radar: a provider with no key, an
 * email that cannot be sent, a removed license key. A kind Radar does not
 * know is dropped, a failed job is saved at once, and a broken queue never
 * breaks the code that raised the issue.
 */
beforeEach(function (): void {
    config(['chatvia.issues.enabled' => true]);
});

it('queues the issues raised across the app', function (): void {
    Queue::fake();
    config(['services.scalewayai.api_key' => null]);

    rescue(fn () => app(LlmProviders::class)->forProvider(LlmProvider::factory()->kind(LlmProviderKind::Scaleway)->create()), report: false);
    report(new TransportException('Connection could not be established with host "smtp.example.com"'));
    IssueRaised::dispatch(ServiceKey::LICENSE, IssueTypeKey::LICENSE_INACTIVE);
    IssueRaised::dispatch('openai', 'made_up_kind');

    expect(Queue::pushed(RecordIssueJob::class)->map(fn (RecordIssueJob $job): array => [$job->issue->issue->service, $job->issue->issue->type])->all())->toBe([
        ['scaleway', IssueTypeKey::KEY_MISSING],
        [ServiceKey::MAIL, IssueTypeKey::SEND_FAILED],
        [ServiceKey::LICENSE, IssueTypeKey::LICENSE_INACTIVE],
    ]);
});

it('saves a failed job at once, as the queue may be what is broken', function (): void {
    Queue::fake();
    $job = Mockery::mock(Job::class)->shouldIgnoreMissing();
    $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\ExampleJob');

    event(new JobFailed('redis', $job, new RuntimeException('The video service took too long to answer.')));

    $issue = Issue::query()->where('kind', IssueTypeKey::JOB_FAILED)->first();

    expect($issue?->subject)->toBe('App\\Jobs\\ExampleJob')
        ->and($issue?->title())->toBe('The background job ExampleJob failed')
        ->and($issue?->message)->toBe('RuntimeException: The video service took too long to answer.');
    Queue::assertNothingPushed();
});

it('never breaks the caller when the queue fails', function (): void {
    $bus = Mockery::mock(Dispatcher::class);
    $bus->shouldReceive('dispatch')->andThrow(new RuntimeException('Redis is down'));
    app()->instance(Dispatcher::class, $bus);

    IssueRaised::dispatch(ServiceKey::LICENSE, IssueTypeKey::LICENSE_INACTIVE);

    expect(true)->toBeTrue();
});
