<?php

declare(strict_types=1);

namespace App\Admin\Issue\Listeners;

use App\Admin\Issue\Services\IssueReporter;
use Illuminate\Queue\Events\JobProcessed;
use Support\Radar\ServiceKey;

/**
 * A job ran fine, so its class's "failed" issue closes. One cache read per
 * job tells whether the class has an open issue at all.
 */
final readonly class CloseRecoveredJob
{
    public function __construct(private IssueReporter $reporter) {}

    public function handle(JobProcessed $event): void
    {
        if (! $event->job->hasFailed()) {
            $this->reporter->recovered(ServiceKey::QUEUE, $event->job->resolveName());
        }
    }
}
