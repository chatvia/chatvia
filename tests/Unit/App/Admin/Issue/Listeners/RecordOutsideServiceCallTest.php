<?php

use App\Admin\Issue\Jobs\RecordIssueJob;
use App\Admin\Issue\Jobs\ResolveRecoveredIssuesJob;
use App\Admin\Issue\Services\IssueRecording;
use App\Admin\Issue\Services\IssueReporter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Support\Radar\IssueTypeKey;

/**
 * Calls made with Laravel's HTTP client: a refusal from a service the admin
 * set up becomes an issue, a success closes the service's open issues, and
 * calls to other hosts or made by a Settings check are left alone.
 */
beforeEach(function (): void {
    config(['chatvia.issues.enabled' => true]);
    Queue::fake();
});

/** The one issue queued by the test. */
function roscQueued(): RecordIssueJob
{
    $jobs = Queue::pushed(RecordIssueJob::class);
    expect($jobs)->toHaveCount(1);

    return $jobs->first();
}

it('turns a refusal an admin can fix into an issue', function (int $status, string $body, string $type): void {
    Http::fake(['queue.fal.run/*' => Http::response($body, $status)]);

    Http::post('https://queue.fal.run/fal-ai/wan/v2.2-a14b/text-to-video?key=secret', ['model' => 'wan', 'prompt' => 'a cat']);

    $issue = roscQueued()->issue->issue;

    expect($issue->service)->toBe('fal')
        ->and($issue->type)->toBe($type)
        ->and($issue->httpStatus)->toBe($status)
        ->and($issue->endpoint)->toBe('queue.fal.run/fal-ai/wan/v2.2-a14b/text-to-video')
        ->and($issue->model)->toBe('wan');
})->with([
    'refused key' => [403, '{"detail":"Invalid key"}', IssueTypeKey::KEY_REFUSED],
    'no credit behind a 403' => [403, '{"detail":"User is locked. Reason: Exhausted balance."}', IssueTypeKey::OUT_OF_CREDIT],
    'too many requests' => [429, '{}', IssueTypeKey::RATE_LIMITED],
    'failing service' => [503, '{}', IssueTypeKey::SERVICE_DOWN],
    'gone model' => [404, '{}', IssueTypeKey::MODEL_GONE],
]);

it('leaves hosts that are not the admin\'s alone, and a 404 without a model', function (): void {
    Http::fake(['*' => Http::response('{}', 404)]);

    Http::get('https://mcp.example.com/tools');
    Http::get('https://queue.fal.run/fal-ai/wan/requests/old/status');

    Queue::assertNothingPushed();
});

it('reports a service that cannot be reached, without the query string', function (): void {
    Http::fake(['api.openai.com/*' => Http::failedConnection('cURL error 28: timed out for https://api.openai.com/v1/responses?x=1')]);

    rescue(fn () => Http::post('https://api.openai.com/v1/responses'), report: false);

    expect(roscQueued()->issue->issue->type)->toBe(IssueTypeKey::UNREACHABLE)
        ->and(roscQueued()->issue->issue->message)->not->toContain('?x=1');
});

it('closes the open issues of a service that answers again', function (): void {
    Http::fake(['queue.fal.run/*' => Http::response('{}')]);
    Http::post('https://queue.fal.run/fal-ai/wan');
    Queue::assertNotPushed(ResolveRecoveredIssuesJob::class);

    Cache::forever(IssueReporter::openKey('fal'), true);
    Http::post('https://queue.fal.run/fal-ai/wan');

    Queue::assertPushed(ResolveRecoveredIssuesJob::class, fn (ResolveRecoveredIssuesJob $job): bool => $job->service === 'fal');
});

it('records nothing for a Settings check', function (): void {
    Http::fake(['api.openai.com/*' => Http::response('{}', 401)]);

    IssueRecording::muted(fn () => Http::get('https://api.openai.com/v1/models'));

    Queue::assertNothingPushed();
});
