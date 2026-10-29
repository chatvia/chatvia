<?php

declare(strict_types=1);

namespace App\Admin\Issue\Listeners;

use App\Admin\Issue\Services\IssueReporter;
use Support\Radar\IssueRaised;

/** Hands a raised issue to Radar ({@see IssueReporter}), from wherever it was raised. */
final readonly class RecordRaisedIssue
{
    public function __construct(private IssueReporter $reporter) {}

    public function handle(IssueRaised $event): void
    {
        $this->reporter->report($event);
    }
}
