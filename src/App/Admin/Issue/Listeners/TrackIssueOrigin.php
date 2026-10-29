<?php

declare(strict_types=1);

namespace App\Admin\Issue\Listeners;

use App\Admin\Issue\Services\IssueOrigin;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

/**
 * Notes which job or command is running, so an issue can say where it
 * happened ({@see IssueOrigin}). A failed job keeps its name until the next
 * one starts: Laravel reports the failure just after the job stops.
 */
final class TrackIssueOrigin
{
    public function handle(JobProcessing|JobProcessed|CommandStarting $event): void
    {
        match (true) {
            $event instanceof JobProcessing => IssueOrigin::jobStarted($event->job->resolveName()),
            $event instanceof JobProcessed => IssueOrigin::jobFinished(),
            default => filled($event->command) ? IssueOrigin::commandStarted($event->command) : null,
        };
    }
}
