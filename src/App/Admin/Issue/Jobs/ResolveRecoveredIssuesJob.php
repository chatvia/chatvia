<?php

declare(strict_types=1);

namespace App\Admin\Issue\Jobs;

use App\Admin\Issue\Actions\ResolveRecoveredIssuesAction;
use App\Admin\Issue\Services\IssueReporter;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;

/**
 * Closes a service's (or one subject's) open issues once it works again
 * ({@see IssueReporter::recovered()}).
 */
#[Queue('records')]
// The next successful call asks again.
#[Tries(1)]
// Every successful call asks, so one job per service covers a burst.
#[UniqueFor(60)]
class ResolveRecoveredIssuesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $service,
        public readonly ?string $subject = null,
        public readonly ?string $type = null,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->service}|{$this->subject}|{$this->type}";
    }

    public function handle(ResolveRecoveredIssuesAction $action): void
    {
        $action->execute($this->service, $this->subject, $this->type);
    }
}
