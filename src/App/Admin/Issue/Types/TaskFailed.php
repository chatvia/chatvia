<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Issue\Data\IssueActionData;
use App\Admin\Issue\Data\IssueSubject;
use App\Admin\Pages\Status;
use Support\Radar\IssueTypeKey;

/**
 * A scheduled task (a nightly prune, the license check…) failed. One issue
 * per task; it closes the next time the task succeeds.
 */
final class TaskFailed extends IssueType
{
    public function key(): string
    {
        return IssueTypeKey::TASK_FAILED;
    }

    public function label(): string
    {
        return 'Task failed';
    }

    public function title(IssueSubject $subject): string
    {
        return "The scheduled task {$subject->subject} failed";
    }

    public function advice(IssueSubject $subject): string
    {
        return "Run it by hand on the server to see the error: php artisan {$subject->subject}. The issue closes by itself the next time the task succeeds.";
    }

    public function actions(IssueSubject $subject): array
    {
        return [new IssueActionData('Open Status', Status::getUrl())];
    }

    public function perSubject(): bool
    {
        return true;
    }
}
