<?php

declare(strict_types=1);

namespace App\Admin\Issue\Listeners;

use App\Admin\Issue\Services\IssueReporter;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Event as ScheduledTask;
use Illuminate\Support\Str;
use Support\Radar\IssueRaised;
use Support\Radar\IssueTypeKey;
use Support\Radar\ServiceKey;

/**
 * A scheduled task ended: one that threw or stopped with an error code is an
 * issue for that task (saved at once, as the queue may be down), and one
 * that succeeded closes it.
 */
final readonly class RecordFinishedTask
{
    public function __construct(private IssueReporter $reporter) {}

    public function handle(ScheduledTaskFinished|ScheduledTaskFailed $event): void
    {
        $task = $this->name($event->task);

        $failure = match (true) {
            $event instanceof ScheduledTaskFailed => Str::limit($event->exception->getMessage(), 300),
            filled($event->task->exitCode) && $event->task->exitCode !== 0 => "It stopped with exit code {$event->task->exitCode}.",
            default => null,
        };

        if ($failure === null) {
            $this->reporter->recovered(ServiceKey::SCHEDULER, $task);

            return;
        }

        $this->reporter->reportNow(new IssueRaised(ServiceKey::SCHEDULER, IssueTypeKey::TASK_FAILED, $failure, subject: $task));
    }

    /** The task as it is run: "chatvia:prune:issues", or its description. */
    private function name(ScheduledTask $task): string
    {
        $command = Str::of((string) $task->command)->after("artisan'")->trim()->toString();

        return Str::limit(filled($command) ? $command : ($task->description ?? $task->getSummaryForDisplay()), 200, '');
    }
}
