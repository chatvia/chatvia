<?php

declare(strict_types=1);

namespace App\Admin\Issue\Listeners;

use App\Admin\Issue\Services\IssueReporter;
use App\Identity\Workspace\Events\MessageLimitThresholdReached;
use Support\Radar\IssueRaised;
use Support\Radar\IssueTypeKey;
use Support\Radar\ServiceKey;

/**
 * A workspace used its whole monthly message limit: an issue, since only an
 * admin can raise it. The 80% warning and a chatter's own limit are left to
 * the workspace.
 */
final readonly class RecordMessageLimitReached
{
    public function __construct(private IssueReporter $reporter) {}

    public function handle(MessageLimitThresholdReached $event): void
    {
        $workspaceId = $event->limit->workspace_id;

        if ($event->percent >= 100 && $workspaceId !== null) {
            $this->reporter->report(new IssueRaised(
                service: ServiceKey::WORKSPACES,
                type: IssueTypeKey::MESSAGE_LIMIT_REACHED,
                message: "All {$event->limit->monthly_limit} messages of this month are used.",
                subject: (string) $workspaceId,
            ));
        }
    }
}
