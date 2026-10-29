<?php

declare(strict_types=1);

namespace App\Admin\Issue\Jobs;

use App\Admin\Issue\Actions\RecordIssueAction;
use App\Admin\Issue\Data\ReportedIssueData;
use App\Admin\Issue\Services\IssueReporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;

/**
 * Saves one service issue as an occurrence, on a worker
 * ({@see IssueReporter}).
 */
// The quick-writes pool, beside the analytics counters and audit rows.
#[Queue('records')]
#[Tries(3)]
#[Backoff(5, 30)]
class RecordIssueJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ReportedIssueData $issue) {}

    public function handle(RecordIssueAction $action): void
    {
        $action->execute($this->issue);
    }
}
