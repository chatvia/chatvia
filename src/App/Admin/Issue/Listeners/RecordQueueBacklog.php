<?php

declare(strict_types=1);

namespace App\Admin\Issue\Listeners;

use App\Admin\Issue\Services\IssueReporter;
use Laravel\Horizon\Events\LongWaitDetected;
use Support\Radar\IssueRaised;
use Support\Radar\IssueTypeKey;
use Support\Radar\ServiceKey;

/**
 * Horizon saw jobs wait on a queue longer than allowed: an issue for that
 * queue. The health check closes it once the wait is short again.
 */
final readonly class RecordQueueBacklog
{
    public function __construct(private IssueReporter $reporter) {}

    public function handle(LongWaitDetected $event): void
    {
        $this->reporter->reportNow(new IssueRaised(
            service: ServiceKey::QUEUE,
            type: IssueTypeKey::QUEUE_BACKLOG,
            message: "Jobs waited {$event->seconds} seconds.",
            subject: "{$event->connection}:{$event->queue}",
        ));
    }
}
