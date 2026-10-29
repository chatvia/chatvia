<?php

declare(strict_types=1);

namespace App\Admin\Issue\Listeners;

use App\Admin\Issue\Jobs\RecordIssueJob;
use App\Admin\Issue\Jobs\ResolveRecoveredIssuesJob;
use App\Admin\Issue\Services\IssueReporter;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Str;
use Support\Radar\IssueRaised;
use Support\Radar\IssueTypeKey;
use Support\Radar\ServiceKey;

/**
 * A background job failed after all its tries: an issue for its job class,
 * saved at once (the queue may be what is broken). Radar's own jobs are left
 * out, so a broken Radar never reports itself.
 */
final readonly class RecordFailedJob
{
    public function __construct(private IssueReporter $reporter) {}

    public function handle(JobFailed $event): void
    {
        $job = $event->job->resolveName();

        if (collect([RecordIssueJob::class, ResolveRecoveredIssuesJob::class])->contains($job)) {
            return;
        }

        $this->reporter->reportNow(new IssueRaised(
            service: ServiceKey::QUEUE,
            type: IssueTypeKey::JOB_FAILED,
            message: Str::of(class_basename($event->exception))->append(': ', $event->exception->getMessage())->limit(300)->toString(),
            subject: $job,
        ));
    }
}
